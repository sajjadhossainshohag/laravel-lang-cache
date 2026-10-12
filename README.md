# Laravel Lang Cache

Cache your Laravel translation files the same way you cache config and routes.

```bash
php artisan lang:cache
```

Laravel reads your language files from disk on every request that translates something. Each JSON file is decoded with `json_decode()`, and each PHP group file is loaded and merged from every path (the framework, your app, packages, and `lang/vendor` overrides). This package compiles all of that into one PHP file at deploy time, so each request only needs a single `require` that OPcache serves straight from memory.

## Benchmark

These numbers come from a 1,000-key `en.json` and five PHP groups with 200 keys each. Every simulated request creates a new translator, then looks up one JSON key and keys from three groups, averaged over 2,000 requests (PHP 8.4, Laravel 13).

| | Laravel `FileLoader` | With `lang:cache` | Speedup |
|---|---|---|---|
| **OPcache enabled** (typical production) | ~600 µs | ~7 µs | **~85×** |
| OPcache disabled | ~900 µs | ~650 µs | ~1.4× |

With OPcache, that saves about **0.6 ms on every request** that translates something, including validation error messages. The gain grows with the size of your JSON files and the number of groups a request uses.

These numbers measure translation loading only, not a full HTTP request. To run the benchmark on your own machine:

```bash
php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 benchmark/run.php
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

The cache file is written to `bootstrap/cache/translations.php`.

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
- At runtime, when the cache file exists, the package replaces Laravel's default translation loader with a loader that reads from the cached array. Locales that aren't in the cache fall back to reading files as usual.
- If the cache file doesn't exist, nothing changes: Laravel's normal loader is used.
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
