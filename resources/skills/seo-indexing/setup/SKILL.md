---
name: seo-indexing-setup
description: Use when installing dancing-janissary/laravel-seo-indexing in a Laravel project for the first time, configuring Google Indexing API credentials, or setting up IndexNow. Triggers on "set up seo indexing", "install seo indexing", "configure google indexing", "configure indexnow".
---

# SEO Indexing — Setup & Configuration

Integrate `dancing-janissary/laravel-seo-indexing` into a Laravel 11 or 12 project. This package submits URLs to Google Indexing API and IndexNow (Bing, Yandex) automatically on Eloquent CRUD events.

## Prerequisites

- PHP 8.2+
- Laravel 11 or 12
- A queue worker (recommended for production — sync mode is supported but blocks requests)

## Install

```bash
composer require dancing-janissary/laravel-seo-indexing
```

Auto-discovery registers the service provider and `SeoIndexing` facade. Verify with `php artisan about | grep -i seo`.

## Publish config and migrations

```bash
# Everything at once
php artisan vendor:publish --tag=seo-indexing

# Or selectively
php artisan vendor:publish --tag=seo-indexing-config
php artisan vendor:publish --tag=seo-indexing-migrations

# Run the migration to create seo_indexing_logs table
php artisan migrate
```

## Configure Google Indexing API

1. Create a project in [Google Cloud Console](https://console.cloud.google.com/) and enable the **Indexing API**.
2. Create a **Service Account** and download the JSON key file.
3. In [Google Search Console](https://search.google.com/search-console), add the service account email as an **Owner** of the property.
4. Store the JSON key **outside** the project root (NEVER commit it to git).
5. Add `*-service-account.json` and `*credentials*.json` to `.gitignore` as a safety net.

```env
GOOGLE_INDEXING_CREDENTIALS_PATH=/etc/google/my-site-indexing-credentials.json
```

> Note: Google officially supports the Indexing API only for **job posting** and **livestream** pages. General pages work in practice but are not guaranteed.

## Configure IndexNow

1. Generate a key (alphanumeric, 8+ chars): `openssl rand -hex 16`
2. Serve a verification file at `https://your-domain.com/{key}.txt` containing only the key as plain text. A Laravel route works:

```php
// routes/web.php
Route::get('/{key}.txt', fn ($key) => $key === config('seo-indexing.indexnow.key')
    ? response($key, 200, ['Content-Type' => 'text/plain'])
    : abort(404)
)->where('key', '[A-Za-z0-9]+');
```

3. Set the key in `.env`:

```env
INDEXNOW_KEY=your_generated_key
```

## Required `.env` variables

```env
# Google
GOOGLE_INDEXING_CREDENTIALS_PATH=/absolute/path/to/credentials.json

# IndexNow
INDEXNOW_KEY=your_indexnow_key

# Queue (strongly recommended)
SEO_INDEXING_QUEUE_ENABLED=true
SEO_INDEXING_QUEUE_CONNECTION=redis
SEO_INDEXING_QUEUE_NAME=indexing

# Log retention (days; 0 = keep forever)
SEO_INDEXING_LOG_RETENTION=30
```

## Queue worker

Run a dedicated worker for the `indexing` queue to keep submissions isolated:

```bash
php artisan queue:work redis --queue=indexing --tries=2 --timeout=60
```

For Supervisor (production):

```ini
[program:seo-indexing-worker]
command=php /var/www/html/artisan queue:work redis --queue=indexing --tries=2 --timeout=60
autostart=true
autorestart=true
numprocs=1
```

## Log pruning

Add to the scheduler to auto-delete logs older than `logging.retention_days`:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;
Schedule::command('model:prune')->daily();
```

## Verify the install

1. Tinker: `php artisan tinker` → `app('seo-indexing')` should resolve a `SeoIndexingManager` instance.
2. Check migration ran: `Schema::hasTable('seo_indexing_logs')` returns `true`.
3. Submit a test URL: `SeoIndexing::submit('https://example.com/test')` — then check `seo_indexing_logs` and `failed_jobs` for results.

## Next steps

- Add the `Indexable` trait to a model — see skill `seo-indexing-integration`.
- Multi-language routes — see skill `seo-indexing-multilang`.
- If something breaks — see skill `seo-indexing-troubleshooting`.
