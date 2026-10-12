<?php

namespace SajjadHossainShohag\LangCache;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;

class CachedFileLoader extends FileLoader
{
    /**
     * The cached translations, keyed by locale, namespace, and group.
     *
     * @var array
     */
    protected $cache;

    /**
     * Create a new cached file loader instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  array|string  $path
     * @param  array  $cache
     */
    public function __construct(Filesystem $files, array|string $path, array $cache)
    {
        parent::__construct($files, $path);

        $this->cache = $cache;
    }

    /**
     * Load the messages for the given locale.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array
     */
    public function load($locale, $group, $namespace = null)
    {
        if (! isset($this->cache[$locale])) {
            return parent::load($locale, $group, $namespace);
        }

        return $this->cache[$locale][$namespace ?? '*'][$group] ?? [];
    }

    /**
     * Get the translation paths of the given file loader.
     *
     * The paths() method was added in Laravel 11.42, so older versions read the property directly.
     *
     * @param  \Illuminate\Translation\FileLoader  $loader
     * @return array
     */
    public static function pathsOf(FileLoader $loader)
    {
        return method_exists($loader, 'paths') ? $loader->paths() : $loader->paths;
    }
}
