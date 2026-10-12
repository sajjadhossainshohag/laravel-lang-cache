<?php

namespace SajjadHossainShohag\LangCache;

use Illuminate\Contracts\Foundation\Application;

class LangCache
{
    /**
     * Get the path to the cached translations file.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return string
     */
    public static function path(Application $app)
    {
        return $app->bootstrapPath('cache/translations.php');
    }

    /**
     * Determine if the translations are cached.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return bool
     */
    public static function isCached(Application $app)
    {
        return is_file(static::path($app));
    }
}
