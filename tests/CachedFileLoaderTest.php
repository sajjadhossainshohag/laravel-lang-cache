<?php

namespace SajjadHossainShohag\LangCache\Tests;

use Illuminate\Filesystem\Filesystem;
use Mockery;
use PHPUnit\Framework\TestCase;
use SajjadHossainShohag\LangCache\CachedFileLoader;

class CachedFileLoaderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testLoadMethodReturnsCachedLinesWithoutReadingFiles()
    {
        $files = Mockery::mock(Filesystem::class);
        $files->shouldNotReceive('exists');
        $files->shouldNotReceive('getRequire');
        $files->shouldNotReceive('get');

        $loader = new CachedFileLoader($files, __DIR__, [
            'en' => [
                '*' => [
                    '*' => ['Hello' => 'Hello'],
                    'messages' => ['foo' => 'bar'],
                    'nested/messages' => ['baz' => 'qux'],
                ],
                'package' => [
                    'messages' => ['foo' => 'package-bar'],
                ],
            ],
        ]);

        $this->assertSame(['Hello' => 'Hello'], $loader->load('en', '*', '*'));
        $this->assertSame(['foo' => 'bar'], $loader->load('en', 'messages', '*'));
        $this->assertSame(['foo' => 'bar'], $loader->load('en', 'messages'));
        $this->assertSame(['baz' => 'qux'], $loader->load('en', 'nested/messages', '*'));
        $this->assertSame(['foo' => 'package-bar'], $loader->load('en', 'messages', 'package'));
    }

    public function testLoadMethodReturnsEmptyArrayForMissingGroupsOfCachedLocales()
    {
        $files = Mockery::mock(Filesystem::class);
        $files->shouldNotReceive('exists');

        $loader = new CachedFileLoader($files, __DIR__, [
            'en' => ['*' => ['messages' => ['foo' => 'bar']]],
        ]);

        $this->assertSame([], $loader->load('en', 'missing', '*'));
        $this->assertSame([], $loader->load('en', '*', '*'));
        $this->assertSame([], $loader->load('en', 'messages', 'package'));
    }

    public function testLocaleFilesAreOnlyLoadedWhenTheLocaleIsUsed()
    {
        $files = Mockery::mock(Filesystem::class);
        $files->shouldNotReceive('exists');

        $path = tempnam(sys_get_temp_dir(), 'lang');
        file_put_contents($path, "<?php return ['*' => ['messages' => ['foo' => 'le bar']]];");

        $loader = new CachedFileLoader($files, __DIR__, [
            'en' => __DIR__.'/this-file-does-not-exist.php',
            'fr' => $path,
        ]);

        try {
            $this->assertSame(['foo' => 'le bar'], $loader->load('fr', 'messages', '*'));
            $this->assertSame([], $loader->load('fr', 'missing', '*'));
        } finally {
            unlink($path);
        }

        // Already loaded, so the deleted file isn't read again...
        $this->assertSame(['foo' => 'le bar'], $loader->load('fr', 'messages', '*'));
    }

    public function testSerializedLocaleFilesCanBeLoaded()
    {
        $files = Mockery::mock(Filesystem::class);
        $files->shouldNotReceive('exists');

        $path = tempnam(sys_get_temp_dir(), 'lang').'.ser';
        file_put_contents($path, serialize(['*' => ['messages' => ['foo' => 'le bar']]]));

        $loader = new CachedFileLoader($files, __DIR__, ['fr' => $path]);

        try {
            $this->assertSame(['foo' => 'le bar'], $loader->load('fr', 'messages', '*'));
        } finally {
            unlink($path);
        }
    }

    public function testSerializedLocaleFilesCannotCreateObjects()
    {
        $path = tempnam(sys_get_temp_dir(), 'lang').'.ser';
        file_put_contents($path, serialize(['*' => ['messages' => ['foo' => new \ArrayObject]]]));

        $loader = new CachedFileLoader(Mockery::mock(Filesystem::class), __DIR__, ['fr' => $path]);

        try {
            $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $loader->load('fr', 'messages', '*')['foo']);
        } finally {
            unlink($path);
        }
    }

    public function testLoadMethodFallsBackToFilesForLocalesThatAreNotCached()
    {
        $files = Mockery::mock(Filesystem::class);
        $files->expects('exists')->with(__DIR__.'/fr/messages.php')->andReturn(true);
        $files->expects('getRequire')->with(__DIR__.'/fr/messages.php')->andReturn(['foo' => 'le bar']);

        $loader = new CachedFileLoader($files, __DIR__, [
            'en' => ['*' => ['messages' => ['foo' => 'bar']]],
        ]);

        $this->assertSame(['foo' => 'le bar'], $loader->load('fr', 'messages', '*'));
    }
}
