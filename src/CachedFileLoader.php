<?php

namespace SajjadHossainShohag\LangCache;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;

class CachedFileLoader extends FileLoader
{
    /**
     * The cached translations, keyed by locale, namespace, and group.
     *
     * A locale may also map to the path of a file that returns its translations. That
     * file is only loaded the first time the locale is used, so a request doesn't pay
     * for the locales it never touches.
     *
     * @var array<string, array|string>
     */
    protected $cache;

    /**
     * Create a new cached file loader instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  array|string  $path
     * @param  array<string, array|string>  $cache
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

        if (is_string($this->cache[$locale])) {
            $this->cache[$locale] = $this->loadFile($this->cache[$locale]);
        }

        return $this->cache[$locale][$namespace ?? '*'][$group] ?? [];
    }

    /**
     * Load the cached translations from the given file.
     *
     * @param  string  $path
     * @return array
     */
    protected function loadFile($path)
    {
        if (str_ends_with($path, '.ser')) {
            return unserialize(file_get_contents($path), ['allowed_classes' => false]);
        }

        return require $path;
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
