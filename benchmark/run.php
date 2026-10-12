<?php

/*
 * Compares the cost of loading translations with Laravel's FileLoader and with the cached loader.
 *
 * Usage:
 *   php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 benchmark/run.php
 *   php -d opcache.enable_cli=0 benchmark/run.php
 *
 * Each simulated request builds a new loader and translator (like a fresh PHP-FPM request),
 * then looks up one JSON key and one key from three different PHP groups.
 */

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use SajjadHossainShohag\LangCache\CachedFileLoader;

$files = new Filesystem;
$dir = sys_get_temp_dir().'/laravel-lang-cache-benchmark';
$groups = ['messages', 'validation', 'auth', 'dashboard', 'billing'];

$files->ensureDirectoryExists("{$dir}/lang/en");

// Fixtures: a 1,000 key JSON file and five PHP groups with 200 keys each...
$json = [];

for ($i = 0; $i < 1000; $i++) {
    $json["Sentence number {$i} with some words"] = "Phrase numéro {$i} avec des mots";
}

$files->put("{$dir}/lang/en.json", json_encode($json));

foreach ($groups as $group) {
    $lines = [];

    for ($i = 0; $i < 200; $i++) {
        $lines["key_{$i}"] = "Value {$i} for {$group} with :name";
    }

    $files->put("{$dir}/lang/en/{$group}.php", '<?php return '.var_export($lines, true).';');
}

// Build the cache the same way "php artisan lang:cache" does...
$loader = new FileLoader($files, ["{$dir}/lang"]);

$cache = ['en' => ['*' => ['*' => $loader->load('en', '*', '*')]]];

foreach ($groups as $group) {
    $cache['en']['*'][$group] = $loader->load('en', $group, '*');
}

$files->put("{$dir}/translations.php", '<?php return '.var_export($cache, true).';');

$request = function (bool $cached) use ($files, $dir) {
    $loader = $cached
        ? new CachedFileLoader($files, ["{$dir}/lang"], require "{$dir}/translations.php")
        : new FileLoader($files, ["{$dir}/lang"]);

    $translator = new Translator($loader, 'en');

    $translator->get('Sentence number 5 with some words');
    $translator->get('messages.key_1');
    $translator->get('validation.key_2');
    $translator->get('auth.key_3');
};

// Warm up OPcache, then measure...
foreach ([false, true] as $cached) {
    for ($i = 0; $i < 200; $i++) {
        $request($cached);
    }
}

$iterations = 2000;
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
    "OPcache %s | FileLoader: %.1f µs | lang:cache: %.1f µs | %.1fx faster\n",
    ini_get('opcache.enable_cli') ? 'on ' : 'off',
    $results['files'],
    $results['cached'],
    $results['files'] / $results['cached'],
);
