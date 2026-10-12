# Laravel Lang Cache

Make your Laravel translations load faster by caching them, the same way you cache your config and routes.

By default, Laravel reads all of your language files again on every request. This package saves them into a cache when you deploy, so your app doesn't have to read them each time. Loading translations then takes about the same short time, however many translations you have.

## Installation

```bash
composer require sajjadhossainshohag/laravel-lang-cache
```

You need PHP 8.1 or newer and Laravel 10, 11, 12, or 13.

## Usage

Create the cache when you deploy:

```bash
php artisan lang:cache
```

Delete the cache:

```bash
php artisan lang:clear
```

If you use Laravel 11.27.1 or newer, `php artisan optimize` creates the cache for you, and `php artisan optimize:clear` deletes it. To check whether your translations are cached, run `php artisan about`.

## How much faster is it?

We tested an app with seven languages and measured how long it takes to load translations on each request (PHP 8.4, Laravel 13).

| Keys per locale | Without cache | With cache | Speedup (OPcache) | Speedup (no OPcache) |
|---|---|---|---|---|
| 2,000 | ~545 µs | ~9 µs | **~62×** | ~2.6× |
| 10,000 | ~1.55 ms | ~9 µs | **~170×** | ~2× |
| 25,000 | ~3.5 ms | ~9 µs | **~380×** | ~1.5× |
| 50,000 | ~7.9 ms | ~10 µs | **~830×** | ~1.9× |
| 75,000 | ~11.6 ms | ~11 µs | **~1,100×** | ~1.5× |
| 100,000 | ~17 ms | ~11 µs | **~1,600×** | ~1.6× |

The times in the table are with OPcache turned on, which most production servers have. They only cover loading translations, not the whole request.

## Good to know

- **Run `php artisan lang:cache` again after you change a language file.** Until you do, your app keeps using the old translations.
- You get exactly the same translations as before, including translations from packages and your overrides in `lang/vendor`.
- Each language is cached in its own file, and a file is only loaded when that language is used.
- If you add translation paths, do it in a service provider so they're included in the cache.
- Language files must only contain text and arrays. They can't contain functions (closures).

## License

MIT. See [LICENSE.md](LICENSE.md).
