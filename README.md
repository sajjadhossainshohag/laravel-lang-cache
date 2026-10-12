# Laravel Lang Cache

Cache your Laravel translation files the same way you cache config and routes.

```bash
php artisan lang:cache
```

Laravel reads your language files from disk on every request that translates something. Each JSON file is decoded with `json_decode()`, and each PHP group file is loaded and merged from every path (the framework, your app, packages, and `lang/vendor` overrides). This package compiles all of that into plain PHP files at deploy time, one per locale, so each request only needs a single `require` that OPcache serves straight from memory.

## Benchmark

The fixtures are a multilingual app with seven locales. Each locale's keys are split evenly between a JSON file and five PHP groups. Every simulated request creates a new translator for the `de` locale (with `en` as the fallback), then looks up one JSON key and keys from three groups (PHP 8.4, Laravel 13).

**With OPcache** (typical production):

| Keys per locale | Laravel `FileLoader` | With `lang:cache` | Speedup |
|---|---|---|---|
| 2,000 | ~545 µs | ~9 µs | **~62×** |
| 10,000 | ~1.55 ms | ~9 µs | **~170×** |
| 25,000 | ~3.5 ms | ~9 µs | **~380×** |
| 50,000 | ~7.9 ms | ~10 µs | **~830×** |
| 75,000 | ~11.6 ms | ~11 µs | **~1,100×** |
| 100,000 | ~17 ms | ~11 µs | **~1,600×** |

**Without OPcache:**

| Keys per locale | Laravel `FileLoader` | With `lang:cache` | Speedup |
|---|---|---|---|
| 2,000 | ~855 µs | ~325 µs | ~2.6× |
| 10,000 | ~2.6 ms | ~1.3 ms | ~2× |
| 25,000 | ~7 ms | ~4.5 ms | ~1.5× |
| 50,000 | ~14 ms | ~7.4 ms | ~1.9× |
| 75,000 | ~20 ms | ~13 ms | ~1.5× |
| 100,000 | ~25 ms | ~16 ms | ~1.6× |

With OPcache, the cached loader takes about the same time no matter how many translations you have, because OPcache serves the cached arrays straight from shared memory. Laravel's file loader has to decode and merge every file again on each request, so its cost grows with the size of your translations. A small app saves about **0.5 ms per request**; an app with 100,000 keys per locale saves about **17 ms**.

Without OPcache, PHP would have to compile the cached PHP files on every request, so the package reads a serialized copy instead, which is more than twice as fast. It's still worth enabling OPcache, though, since it speeds up your whole application, not just translations.

These numbers measure translation loading only, not a full HTTP request. Each row is the average of two runs; the smaller sizes vary by a few percent, while the larger ones without OPcache vary more. To run the benchmark on your own machine, pass the number of keys per locale (2,000 by default):

```bash
php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 benchmark/run.php 10000
```

## Installation

```bash
composer require sajjadhossainshohag/laravel-lang-cache
```

The service provider is registered automatically. Requires PHP 8.1+ and Laravel 10, 11, 12, or 13.

## Usage

Build the cache as part of your deployment:

```bash
php artisan lang:cache
```

Remove it:

```bash
php artisan lang:clear
```

The cache is written to `bootstrap/cache/translations/`, with one file per locale.

### `optimize` support

The commands are registered with Laravel's optimize commands, so you don't need a separate deploy step if you already use them:

```bash
php artisan optimize        # now also runs lang:cache
php artisan optimize:clear  # now also runs lang:clear
```

To skip it, use `php artisan optimize --except=translations`.

> [!NOTE]
> Packages can only hook into `optimize` on Laravel 11.27.1 and newer. On older versions, `lang:cache` and `lang:clear` work the same, but you need to run them yourself as part of your deployment.

### `about` command

`php artisan about` shows whether translations are cached in the **Cache** section.

## How it works

- `lang:cache` finds every PHP and JSON translation file in your app's lang path, the framework's lang path, package namespaces (`loadTranslationsFrom`), package JSON paths (`loadJsonTranslationsFrom`), and `lang/vendor` overrides.
- Each group is loaded through Laravel's own `FileLoader`, so the cached lines are **exactly** what Laravel would have loaded, with the same merge order and override rules.
- Each locale is written to its own file, plus a small manifest listing the cached locales. Every locale is saved twice: as a PHP file, which OPcache can serve straight from memory, and as a serialized file, which loads faster when OPcache is disabled. The package picks the right one automatically.
- At runtime, when the cache exists, the package replaces Laravel's default translation loader with a loader that reads from the cached files. A locale's file is only loaded the first time that locale is used, so a request doesn't pay for languages it never touches. Locales that aren't in the cache fall back to reading files as usual.
- If the cache doesn't exist, nothing changes: Laravel's normal loader is used.
- If your app binds its own custom translation loader, the package leaves it alone.

## Things to know

- **Re-run `lang:cache` after changing language files.** Just like `config:cache`, edits to language files have no effect until you rebuild or clear the cache. Don't cache translations in local development.
- **Paths added at runtime.** Translation paths or namespaces registered after the cache was built (for example, `Lang::addPath()` inside a controller) aren't in the cache for cached locales. Register them in a service provider, then rebuild the cache.
- **Only plain values can be cached.** Translation files must return arrays of strings, numbers, and arrays. If a file contains something that can't be exported, like a closure, `lang:cache` fails with an error and doesn't write a broken cache file.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for more information.
