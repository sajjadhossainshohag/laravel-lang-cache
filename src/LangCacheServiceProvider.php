<?php

namespace SajjadHossainShohag\LangCache;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use SajjadHossainShohag\LangCache\Console\LangCacheCommand;
use SajjadHossainShohag\LangCache\Console\LangClearCommand;

class LangCacheServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        // Only the framework's default loader is replaced. If the application binds its own
        // loader we leave it alone, since the cache only knows how to mirror a file loader.
        $this->app->extend('translation.loader', function ($loader, $app) {
            if (get_class($loader) !== FileLoader::class || ! LangCache::isCached($app)) {
                return $loader;
            }

            $cached = new CachedFileLoader($app['files'], CachedFileLoader::pathsOf($loader), LangCache::files($app));

            foreach ($loader->jsonPaths() as $path) {
                $cached->addJsonPath($path);
            }

            foreach ($loader->namespaces() as $namespace => $hint) {
                $cached->addNamespace($namespace, $hint);
            }

            return $cached;
        });
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            LangCacheCommand::class,
            LangClearCommand::class,
        ]);

        // Packages can hook into "optimize" since Laravel 11.27.1...
        if (method_exists($this, 'optimizes')) {
            $this->optimizes(
                optimize: 'lang:cache',
                clear: 'lang:clear',
                key: 'translations',
            );
        }

        AboutCommand::add('Cache', fn () => [
            'Translations' => LangCache::isCached($this->app)
                ? '<fg=green;options=bold>CACHED</>'
                : '<fg=yellow;options=bold>NOT CACHED</>',
        ]);
    }
}
