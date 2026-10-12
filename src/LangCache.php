<?php

namespace SajjadHossainShohag\LangCache;

use Illuminate\Contracts\Foundation\Application;

class LangCache
{
    /**
     * Get the path to the directory that holds the cached translations.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return string
     */
    public static function path(Application $app)
    {
        return $app->bootstrapPath('cache/translations');
    }

    /**
     * Get the path to the manifest of cached locales.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return string
     */
    public static function manifestPath(Application $app)
    {
        return static::path($app).'/manifest.php';
    }

    /**
     * Get the path to the cached translations of the given locale.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  string  $locale
     * @return string
     */
    public static function localePath(Application $app, $locale)
    {
        return static::path($app)."/{$locale}.php";
    }

    /**
     * Get the path to the serialized translations of the given locale.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  string  $locale
     * @return string
     */
    public static function serializedPath(Application $app, $locale)
    {
        return static::path($app)."/{$locale}.ser";
    }

    /**
     * Determine if OPcache is caching scripts in the current process.
     *
     * @return bool
     */
    public static function opcacheEnabled()
    {
        if (! function_exists('opcache_get_status') || ! filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return PHP_SAPI !== 'cli' || filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Determine if the translations are cached.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return bool
     */
    public static function isCached(Application $app)
    {
        return is_file(static::manifestPath($app));
    }

    /**
     * Get the cached translation files, keyed by locale.
     *
     * With OPcache, the PHP files are fastest since OPcache serves their arrays from shared
     * memory. Without it, PHP would have to compile them on every request, and reading the
     * serialized copies is more than twice as fast.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return array<string, string>
     */
    public static function files(Application $app)
    {
        $files = [];

        $opcache = static::opcacheEnabled();

        foreach (require static::manifestPath($app) as $locale) {
            $files[$locale] = $opcache
                ? static::localePath($app, $locale)
                : static::serializedPath($app, $locale);
        }

        return $files;
    }
}
