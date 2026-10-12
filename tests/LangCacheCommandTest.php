<?php

namespace SajjadHossainShohag\LangCache\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use LogicException;
use SajjadHossainShohag\LangCache\CachedFileLoader;
use SajjadHossainShohag\LangCache\LangCache;

class LangCacheCommandTest extends TestCase
{
    protected $langPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->langPath = sys_get_temp_dir().'/laravel-lang-cache-'.uniqid();

        $this->writeFile("{$this->langPath}/lang/en/messages.php", "<?php return ['foo' => 'bar', 'nested' => ['a' => 'b']];");
        $this->writeFile("{$this->langPath}/lang/en/admin/users.php", "<?php return ['title' => 'Users'];");
        $this->writeFile("{$this->langPath}/lang/fr/messages.php", "<?php return ['foo' => 'le bar'];");
        $this->writeFile("{$this->langPath}/lang/en.json", json_encode(['Hello' => 'Hello', 'Bye' => 'Bye']));
        $this->writeFile("{$this->langPath}/lang/vendor/package/en/messages.php", "<?php return ['foo' => 'overridden'];");
        $this->writeFile("{$this->langPath}/package/en/messages.php", "<?php return ['foo' => 'package', 'bar' => 'baz'];");
        $this->writeFile("{$this->langPath}/json/en.json", json_encode(['Hello' => 'Package hello', 'Extra' => 'Extra']));

        $this->app->useLangPath("{$this->langPath}/lang");

        $this->refreshTranslator();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->langPath);

        (new Filesystem)->deleteDirectory(LangCache::path($this->app));

        parent::tearDown();
    }

    protected function writeFile($path, $contents)
    {
        $files = new Filesystem;

        $files->ensureDirectoryExists(dirname($path));

        $files->put($path, $contents);
    }

    protected function refreshTranslator()
    {
        $this->app->forgetInstance('translator');
        $this->app->forgetInstance('translation.loader');

        $this->app['translator']->addNamespace('package', "{$this->langPath}/package");
        $this->app['translator']->addJsonPath("{$this->langPath}/json");
    }

    public function testTranslationsAreCachedWithTheSameLinesAsTheFileLoader()
    {
        $loader = $this->app['translation.loader'];

        $this->artisan('lang:cache')->assertSuccessful();

        $this->assertSame(['en', 'fr'], require LangCache::manifestPath($this->app));
        $this->assertFileExists(LangCache::localePath($this->app, 'en'));
        $this->assertFileExists(LangCache::localePath($this->app, 'fr'));

        $cached = new CachedFileLoader(new Filesystem, CachedFileLoader::pathsOf($loader), LangCache::files($this->app));

        $fresh = new FileLoader(new Filesystem, CachedFileLoader::pathsOf($loader));
        $fresh->addNamespace('package', "{$this->langPath}/package");
        $fresh->addJsonPath("{$this->langPath}/json");

        foreach ([
            ['en', '*', '*'],
            ['en', 'messages', '*'],
            ['en', 'admin/users', '*'],
            ['en', 'validation', '*'],
            ['en', 'missing', '*'],
            ['en', 'messages', 'package'],
            ['fr', 'messages', '*'],
            ['fr', '*', '*'],
            ['fr', 'messages', 'package'],
        ] as [$locale, $group, $namespace]) {
            $this->assertSame(
                $fresh->load($locale, $group, $namespace),
                $cached->load($locale, $group, $namespace),
                "Cached lines differ for [{$locale}] [{$namespace}::{$group}]."
            );
        }

        foreach (['en', 'fr'] as $locale) {
            $this->assertSame(
                require LangCache::localePath($this->app, $locale),
                unserialize(file_get_contents(LangCache::serializedPath($this->app, $locale)))
            );
        }

        $this->assertSame('overridden', $cached->load('en', 'messages', 'package')['foo']);
        $this->assertSame('Hello', $cached->load('en', '*', '*')['Hello']);
        $this->assertSame('Extra', $cached->load('en', '*', '*')['Extra']);
    }

    public function testTranslatorUsesTheFileLoaderWhenTranslationsAreNotCached()
    {
        $this->assertFalse(LangCache::isCached($this->app));
        $this->assertSame(FileLoader::class, get_class($this->app['translation.loader']));
        $this->assertSame('bar', __('messages.foo'));
        $this->assertSame('Hello', __('Hello'));
    }

    public function testTranslatorUsesTheCacheWhenTranslationsAreCached()
    {
        $this->artisan('lang:cache')->assertSuccessful();

        $this->refreshTranslator();

        $this->assertInstanceOf(CachedFileLoader::class, $this->app['translation.loader']);
        $this->assertSame('bar', __('messages.foo'));
        $this->assertSame('Users', __('admin/users.title'));
        $this->assertSame('le bar', __('messages.foo', [], 'fr'));
        $this->assertSame('Hello', __('Hello'));
        $this->assertSame('overridden', __('package::messages.foo'));
        $this->assertSame('baz', __('package::messages.bar'));
    }

    public function testTheFormatIsChosenByWhetherOpcacheIsEnabled()
    {
        $this->artisan('lang:cache')->assertSuccessful();

        $extension = LangCache::opcacheEnabled() ? '.php' : '.ser';

        foreach (LangCache::files($this->app) as $path) {
            $this->assertStringEndsWith($extension, $path);
        }
    }

    public function testBothFormatsReturnTheSameLines()
    {
        $this->artisan('lang:cache')->assertSuccessful();

        $paths = CachedFileLoader::pathsOf($this->app['translation.loader']);

        $php = new CachedFileLoader(new Filesystem, $paths, [
            'en' => LangCache::localePath($this->app, 'en'),
        ]);

        $serialized = new CachedFileLoader(new Filesystem, $paths, [
            'en' => LangCache::serializedPath($this->app, 'en'),
        ]);

        foreach ([['*', '*'], ['messages', '*'], ['admin/users', '*'], ['messages', 'package']] as [$group, $namespace]) {
            $this->assertSame($php->load('en', $group, $namespace), $serialized->load('en', $group, $namespace));
        }

        $this->assertSame('bar', $serialized->load('en', 'messages', '*')['foo']);
    }

    public function testCacheIsRebuiltFromFilesWhenAlreadyCached()
    {
        $this->artisan('lang:cache')->assertSuccessful();

        $this->refreshTranslator();

        $this->writeFile("{$this->langPath}/lang/en/messages.php", "<?php return ['foo' => 'changed'];");

        $this->artisan('lang:cache')->assertSuccessful();

        $this->refreshTranslator();

        $this->assertSame('changed', __('messages.foo'));
    }

    public function testCustomLoadersAreNotReplaced()
    {
        $this->artisan('lang:cache')->assertSuccessful();

        $custom = new class(new Filesystem, $this->langPath) extends FileLoader {};

        $this->app->forgetInstance('translator');
        $this->app->forgetInstance('translation.loader');
        $this->app->singleton('translation.loader', fn () => $custom);

        $this->assertSame($custom, $this->app['translation.loader']);
    }

    public function testCacheCanBeCleared()
    {
        $this->artisan('lang:cache')->assertSuccessful();
        $this->assertTrue(LangCache::isCached($this->app));

        $this->artisan('lang:clear')->assertSuccessful();
        $this->assertFalse(LangCache::isCached($this->app));
        $this->assertDirectoryDoesNotExist(LangCache::path($this->app));
    }

    public function testOptimizeCachesAndClearsTranslations()
    {
        if (! method_exists(ServiceProvider::class, 'optimizes')) {
            $this->markTestSkipped('Packages can only hook into "optimize" since Laravel 11.27.1.');
        }

        Artisan::call('optimize', ['--except' => 'config,events,routes,views']);
        $this->assertTrue(LangCache::isCached($this->app));

        Artisan::call('optimize:clear', ['--except' => 'cache,compiled,config,events,routes,views']);
        $this->assertFalse(LangCache::isCached($this->app));
    }

    public function testAboutCommandShowsTheCacheStatus()
    {
        $this->artisan('about', ['--only' => 'cache'])
            ->expectsOutputToContain('Translations')
            ->assertSuccessful();
    }

    public function testNonSerializableTranslationsAreNotCached()
    {
        $this->writeFile("{$this->langPath}/lang/en/closure.php", "<?php return ['foo' => fn () => 'bar'];");

        try {
            $this->artisan('lang:cache');

            $this->fail('Expected a LogicException.');
        } catch (LogicException $e) {
            $this->assertSame('Your translation files are not serializable.', $e->getMessage());
        }

        $this->assertFalse(LangCache::isCached($this->app));
        $this->assertDirectoryDoesNotExist(LangCache::path($this->app));
    }
}
