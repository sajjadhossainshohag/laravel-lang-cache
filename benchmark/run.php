<?php

/*
 * Compares the cost of loading translations with Laravel's FileLoader and with the cached loader.
 *
 * Usage:
 *   php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 benchmark/run.php [keys]
 *   php -d opcache.enable_cli=0 benchmark/run.php [keys]
 *
 * The fixtures are a multilingual app with seven locales. Each locale has [keys] translation
 * keys (2,000 by default): half in a JSON file and half spread across five PHP groups. Each
 * simulated request builds a new loader and translator (like a fresh PHP-FPM request), then
 * looks up one JSON key and keys from three groups.
 */

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use SajjadHossainShohag\LangCache\CachedFileLoader;
use SajjadHossainShohag\LangCache\LangCache;

$files = new Filesystem;
$dir = sys_get_temp_dir().'/laravel-lang-cache-benchmark';
$locales = ['en', 'de', 'es', 'fr', 'ja', 'nl', 'pt_BR'];
$groups = ['messages', 'validation', 'auth', 'dashboard', 'billing'];
$keys = (int) ($argv[1] ?? 2000);
$jsonKeys = intdiv($keys, 2);
$groupKeys = intdiv($keys - $jsonKeys, count($groups));

$files->deleteDirectory($dir);

// Fixtures...
foreach ($locales as $locale) {
    $files->ensureDirectoryExists("{$dir}/lang/{$locale}");

    $json = [];

    for ($i = 0; $i < $jsonKeys; $i++) {
        $json["Sentence number {$i} with some words"] = "[{$locale}] Sentence number {$i} with some words";
    }

    $files->put("{$dir}/lang/{$locale}.json", json_encode($json));

    foreach ($groups as $group) {
        $lines = [];

        for ($i = 0; $i < $groupKeys; $i++) {
            $lines["key_{$i}"] = "[{$locale}] Value {$i} for {$group} with :name";
        }

        $files->put("{$dir}/lang/{$locale}/{$group}.php", '<?php return '.var_export($lines, true).';');
    }
}

// Build the cache the same way "php artisan lang:cache" does: one file per locale...
$loader = new FileLoader($files, ["{$dir}/lang"]);

$files->ensureDirectoryExists("{$dir}/cache");

foreach ($locales as $locale) {
    $lines = ['*' => ['*' => $loader->load($locale, '*', '*')]];

    foreach ($groups as $group) {
        $lines['*'][$group] = $loader->load($locale, $group, '*');
    }

    $files->put("{$dir}/cache/{$locale}.php", '<?php return '.var_export($lines, true).';');
    $files->put("{$dir}/cache/{$locale}.ser", serialize($lines));
}

$files->put("{$dir}/cache/manifest.php", '<?php return '.var_export($locales, true).';');

$request = function (bool $cached) use ($files, $dir) {
    if ($cached) {
        $cache = [];

        $extension = LangCache::opcacheEnabled() ? 'php' : 'ser';

        foreach (require "{$dir}/cache/manifest.php" as $locale) {
            $cache[$locale] = "{$dir}/cache/{$locale}.{$extension}";
        }

        $loader = new CachedFileLoader($files, ["{$dir}/lang"], $cache);
    } else {
        $loader = new FileLoader($files, ["{$dir}/lang"]);
    }

    $translator = new Translator($loader, 'de');

    $translator->setFallback('en');

    $translator->get('Sentence number 5 with some words');
    $translator->get('messages.key_1');
    $translator->get('validation.key_2');
    $translator->get('auth.key_3');
};

// Larger fixtures take longer per request, so they get fewer iterations...
$iterations = max(50, intdiv(4000000, $keys));

// Warm up OPcache, then measure...
foreach ([false, true] as $cached) {
    for ($i = 0; $i < max(10, intdiv($iterations, 10)); $i++) {
        $request($cached);
    }
}
$results = [];

foreach ([false, true] as $cached) {
    $start = hrtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $request($cached);
    }

    $results[$cached ? 'cached' : 'files'] = (hrtime(true) - $start) / $iterations / 1000;
}

$files->deleteDirectory($dir);

printf(
    "OPcache %s | %7s keys per locale | FileLoader: %9.1f µs | lang:cache: %8.1f µs | %5.1fx faster\n",
    ini_get('opcache.enable_cli') ? 'on ' : 'off',
    number_format($jsonKeys + $groupKeys * count($groups)),
    $results['files'],
    $results['cached'],
    $results['files'] / $results['cached'],
);
