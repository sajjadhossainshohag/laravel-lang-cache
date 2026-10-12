# Laravel Lang Cache

Cache your Laravel translation files, just like `config:cache` and `route:cache`.

Laravel reads and merges your language files on every request. This package compiles them once at deploy time, so loading translations takes about 10 µs, no matter how many you have.

## Installation

```bash
composer require sajjadhossainshohag/laravel-lang-cache
```

Requires PHP 8.1+ and Laravel 10–13.

## Usage

```bash
php artisan lang:cache   # build the cache
php artisan lang:clear   # remove it
```

On Laravel 11.27.1 and newer, `php artisan optimize` and `optimize:clear` run these for you. `php artisan about` shows whether translations are cached.

## Benchmark

Time to load translations per request, for an app with seven locales (PHP 8.4, Laravel 13):

| Keys per locale | Without cache | With cache | Speedup (OPcache) | Speedup (no OPcache) |
|---|---|---|---|---|
| 2,000 | ~545 µs | ~9 µs | **~62×** | ~2.6× |
| 10,000 | ~1.55 ms | ~9 µs | **~170×** | ~2× |
| 25,000 | ~3.5 ms | ~9 µs | **~380×** | ~1.5× |
| 50,000 | ~7.9 ms | ~10 µs | **~830×** | ~1.9× |
| 75,000 | ~11.6 ms | ~11 µs | **~1,100×** | ~1.5× |
| 100,000 | ~17 ms | ~11 µs | **~1,600×** | ~1.6× |

Times are with OPcache enabled. They cover translation loading only, not a full request.

## Good to know

- **Re-run `lang:cache` after editing language files**, just like `config:cache`.
- The cache is built with Laravel's own loader, so it returns exactly the same translations, including package and `lang/vendor` overrides.
- Each locale gets its own file and is only loaded when used. Without OPcache, a faster serialized copy is used automatically.
- Register translation paths in a service provider, not at runtime, so they're included in the cache.
- Translation files must return plain values (strings and arrays), not closures.

## License

MIT. See [LICENSE.md](LICENSE.md).
