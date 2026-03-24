# laravel-seo-indexing

[![Tests](https://github.com/dancing-janissary/laravel-seo-indexing/actions/workflows/tests.yml/badge.svg)](https://github.com/dancing-janissary/laravel-seo-indexing/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/dancing-janissary/laravel-seo-indexing.svg)](https://packagist.org/packages/dancing-janissary/laravel-seo-indexing)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue)](https://www.php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E11.0-red)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Automatically notify **Google Indexing API** and **IndexNow** (Bing, Yandex, Seznam, Naver) whenever your Eloquent models are created, updated, or deleted. Attach a single trait to any model and your pages are indexed without a single extra line of code.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [Google Indexing API Setup](#google-indexing-api-setup)
  - [IndexNow Setup](#indexnow-setup)
  - [Environment Variables](#environment-variables)
- [Usage](#usage)
  - [The Indexable Trait](#the-indexable-trait)
  - [Controlling Which Pages Get Indexed](#controlling-which-pages-get-indexed)
  - [Manual Submission via Facade](#manual-submission-via-facade)
  - [Batch Submission](#batch-submission)
  - [Multi-Language Routes](#multi-language-routes)
  - [Disabling Indexing for Bulk Operations](#disabling-indexing-for-bulk-operations)
- [Queue Setup](#queue-setup)
- [Logging & Querying Submission History](#logging--querying-submission-history)
- [Architecture & Design Decisions](#architecture--design-decisions)
- [API Quotas & Limits](#api-quotas--limits)
- [Testing](#testing)
- [Changelog](#changelog)
- [License](#license)

---

## Features

- ✅ **Dual-engine** — submits to both Google Indexing API v3 and IndexNow in one operation
- ✅ **Zero-config CRUD hooks** — attach `Indexable` trait and forget about it
- ✅ **Queue-first** — all submissions dispatched as background jobs with automatic retry
- ✅ **Sync fallback** — disable queues entirely for simple setups or local dev
- ✅ **Per-model control** — `shouldIndex()`, `getIndexableUrl()`, and `withoutIndexing()` give fine-grained control
- ✅ **SoftDeletes aware** — handles `deleted`, `restored` events automatically
- ✅ **Full submission log** — every API call recorded to DB with engine, status, and response payload
- ✅ **Auto-pruning** — configurable log retention via Laravel's built-in model pruning
- ✅ **Deduplication** — skips re-submission if the same URL was successfully submitted recently
- ✅ **Multi-engine IndexNow** — pings Bing, Yandex, and others in a single batch request
- ✅ **Multi-language routes** — submit all locale-specific URLs (e.g. `/en/page`, `/fr/page`) in one batch when a model changes

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.2` |
| Laravel | `^11.0` |
| Google Service Account | Required for Google Indexing API |
| IndexNow API Key | Required for IndexNow (Bing, Yandex, etc.) |

---

## Installation

Install via Composer:

```bash
composer require dancing-janissary/laravel-seo-indexing
```

Laravel's auto-discovery will register the service provider and `SeoIndexing` facade automatically.

Publish the config file and migrations:

```bash
# Publish everything at once
php artisan vendor:publish --tag=seo-indexing

# Or selectively
php artisan vendor:publish --tag=seo-indexing-config
php artisan vendor:publish --tag=seo-indexing-migrations
```

Run the migrations:

```bash
php artisan migrate
```

---

## Configuration

After publishing, the config file is located at `config/seo-indexing.php`.

### Google Indexing API Setup

The Google Indexing API requires a **Service Account** with domain-wide delegation. Follow these steps:

1. Go to the [Google Cloud Console](https://console.cloud.google.com/) and create a project
2. Enable the **Indexing API** for your project
3. Create a **Service Account** and download the JSON credentials file
4. In [Google Search Console](https://search.google.com/search-console), add the service account email as an **Owner** of your property
5. Store the JSON key file somewhere safe on your server — **never inside your project root or git repo**

```bash
# Example: store outside the web root
/etc/google/my-site-indexing-credentials.json
```

Set the path in your `.env`:

```env
GOOGLE_INDEXING_CREDENTIALS_PATH=/etc/google/my-site-indexing-credentials.json
```

> ⚠️ **Security:** The credentials JSON file contains a private key. Never commit it to version control. Add `*-service-account.json` and `*credentials*.json` to your `.gitignore`.

---

### IndexNow Setup

IndexNow uses a simple API key for authentication. The key must be served as a text file at your domain root so search engines can verify ownership.

1. Generate a key — must be alphanumeric, minimum 8 characters:

```bash
# Generate a random key
openssl rand -hex 16
```

2. Create a verification file at your domain root:

```
https://example.com/{your-key}.txt
```

The file must contain only the key itself as plain text.

3. Set your key in `.env`:

```env
INDEXNOW_KEY=your_key_here
```

> **Tip:** You only need to verify with one IndexNow engine — all others accept the same key once verified. The package submits to Bing, Yandex, and `api.indexnow.org` by default.

---

### Environment Variables

Add these to your `.env` file:

```env
# Google Indexing API
GOOGLE_INDEXING_CREDENTIALS_PATH=/absolute/path/to/credentials.json

# IndexNow
INDEXNOW_KEY=your_indexnow_key
INDEXNOW_KEY_FILE=your_indexnow_key.txt   # optional, defaults to {key}.txt

# Queue (recommended for production)
SEO_INDEXING_QUEUE_ENABLED=true
SEO_INDEXING_QUEUE_CONNECTION=redis        # or database, sqs, etc.
SEO_INDEXING_QUEUE_NAME=indexing           # dedicated queue name

# Log retention
SEO_INDEXING_LOG_RETENTION=30              # days, 0 = keep forever
```

---

### Full Config Reference

```php
// config/seo-indexing.php

return [

    // Enable or disable engines globally
    'engines' => [
        'google'   => true,
        'indexnow' => true,
    ],

    'google' => [
        'credentials_path' => env('GOOGLE_INDEXING_CREDENTIALS_PATH'),
        'scopes'           => ['https://www.googleapis.com/auth/indexing'],
    ],

    'indexnow' => [
        'key'      => env('INDEXNOW_KEY'),
        'key_file' => env('INDEXNOW_KEY_FILE', null),
        'host'     => env('APP_URL'),
        'engines'  => [
            'https://api.indexnow.org/indexnow',
            'https://www.bing.com/indexnow',
            'https://yandex.com/indexnow',
        ],
    ],

    'queue' => [
        'enabled'     => env('SEO_INDEXING_QUEUE_ENABLED', true),
        'connection'  => env('SEO_INDEXING_QUEUE_CONNECTION', 'default'),
        'name'        => env('SEO_INDEXING_QUEUE_NAME', 'indexing'),
        'retry_after' => 90,
    ],

    'logging' => [
        'enabled'        => true,
        'retention_days' => env('SEO_INDEXING_LOG_RETENTION', 30),
    ],

    'http' => [
        'timeout'         => 30,
        'connect_timeout' => 10,
        'retry' => [
            'times' => 3,
            'sleep' => 1000,
        ],
    ],
];
```

---

## Usage

### The Indexable Trait

Add the `Indexable` trait to any Eloquent model whose URLs should be submitted to search engines:

```php
use DancingJanissary\SeoIndexing\Traits\Indexable;

class Page extends Model
{
    use Indexable;
}
```

That's it. The following events are now wired automatically:

| Eloquent Event | Action Sent |
|---|---|
| `created` / `updated` | `URL_UPDATED` |
| `deleted` | `URL_DELETED` |
| `restored` *(SoftDeletes)* | `URL_UPDATED` |

By default the URL is built from the model's `slug` attribute (or its primary key as a fallback). Override `getIndexableUrl()` to return the correct public URL for your model:

```php
class Page extends Model
{
    use Indexable;

    public function getIndexableUrl(): string
    {
        return route('pages.show', $this->slug);
    }
}
```

Or set a URL prefix to use the default slug-based URL generation:

```php
protected function getIndexablePrefix(): string
{
    return '/blog';
    // Produces: https://example.com/blog/{slug}
}
```

---

### Controlling Which Pages Get Indexed

Override `shouldIndex()` to add conditions. Only return `true` when the page should actually be visible to search engines:

```php
class Page extends Model
{
    use Indexable;

    public function shouldIndex(): bool
    {
        return parent::shouldIndex()
            && $this->status === 'published'
            && ! $this->is_private;
    }
}
```

When `shouldIndex()` returns `false`, no job is dispatched and no log entry is written.

---

### Manual Submission via Facade

Use the `SeoIndexing` facade to submit URLs outside of model events — useful in controllers, commands, or observers:

```php
use DancingJanissary\SeoIndexing\Facades\SeoIndexing;

// Submit a URL as updated
SeoIndexing::submit('https://example.com/page');

// Submit a URL as deleted
SeoIndexing::delete('https://example.com/old-page');
```

You can also trigger indexing directly on a model instance:

```php
use DancingJanissary\SeoIndexing\SeoIndexingManager;

// Submit as updated
$page->index();

// Submit as deleted
$page->index(SeoIndexingManager::ACTION_DELETED);
```

---

### Batch Submission

Submit multiple URLs in one call. IndexNow supports native batch requests (up to 10,000 URLs); Google sends individual requests per URL internally.

```php
SeoIndexing::submitBatch([
    'https://example.com/page-one',
    'https://example.com/page-two',
    'https://example.com/page-three',
]);

SeoIndexing::deleteBatch([
    'https://example.com/removed-one',
    'https://example.com/removed-two',
]);
```

---

### Multi-Language Routes

If your application serves content in multiple languages with locale-prefixed URLs (e.g. `/en/page`, `/fr/page`, `/de/page`), override `getIndexableUrls()` to return all locale variants. When a model is created, updated, or deleted, all URLs are submitted as a batch:

```php
class Page extends Model
{
    use Indexable;

    public function getIndexableUrls(): ?array
    {
        return collect(['en', 'fr', 'de'])->mapWithKeys(fn ($locale) => [
            $locale => route('pages.show', ['locale' => $locale, 'slug' => $this->slug]),
        ])->all();
    }
}
```

This produces:
```
https://example.com/en/my-page
https://example.com/fr/my-page
https://example.com/de/my-page
```

All three URLs are submitted together via `submitBatch()` whenever the model fires a `saved`, `deleted`, or `restored` event.

**How it works:**

- Return `null` (default) to use the single-URL `getIndexableUrl()` behavior — fully backward compatible
- Return an associative array keyed by locale (keys are for your convenience; only the URL values are submitted)
- Return an empty array to fall back to single-URL mode
- The `index()` method also respects `getIndexableUrls()` for manual submissions

> **Note:** Neither Google Indexing API nor IndexNow accept hreflang metadata. They only receive URLs. For search engines to understand locale relationships, ensure your HTML includes proper `<link rel="alternate" hreflang="...">` tags. The Indexing API tells Google *"crawl this URL now"* — Google discovers hreflang annotations when it crawls the page.

---

### Disabling Indexing for Bulk Operations

When importing or seeding large numbers of records, disable indexing to avoid exhausting API quotas:

```php
// Option A — static disable/enable
Page::disableIndexing();

foreach ($importData as $row) {
    Page::create($row);
}

Page::enableIndexing();
```

```php
// Option B — closure (re-enables automatically, even if an exception is thrown)
$page->withoutIndexing(function () use ($page) {
    $page->update(['status' => 'draft']);
});
```

---

## Queue Setup

Queue-based submissions are strongly recommended for production. Without a queue, every model save blocks the request while waiting for Google's API response (typically 1–3 seconds).

### Why queues?

| | Sync | Queue |
|---|---|---|
| Request speed | Slows down (API latency) | Instant return |
| Failure handling | Lost on timeout | Auto-retry with backoff |
| Bulk imports | Blocks until all submitted | Non-blocking |
| Visibility | None | `failed_jobs` table |

### Dedicated queue worker

Run a dedicated worker for the `indexing` queue to keep SEO submissions isolated from your main application jobs:

```bash
php artisan queue:work redis --queue=indexing --tries=2 --timeout=60
```

For production with Supervisor, add a separate program block:

```ini
[program:seo-indexing-worker]
command=php /var/www/html/artisan queue:work redis --queue=indexing --tries=2 --timeout=60
autostart=true
autorestart=true
numprocs=1
```

### Log retention (auto-pruning)

Add `model:prune` to your scheduler to automatically clean up old log entries based on the `logging.retention_days` config value:

```php
// routes/console.php
Schedule::command('model:prune')->daily();
```

---

## Logging & Querying Submission History

Every API submission — whether successful or failed — is recorded in the `seo_indexing_logs` table. Use the `SeoIndexingLog` model to query the history:

```php
use DancingJanissary\SeoIndexing\Models\SeoIndexingLog;

// All failed submissions in the last 7 days
SeoIndexingLog::failed()->recent(7)->get();

// All Google failures
SeoIndexingLog::failed()->forEngine('google')->latest()->get();

// Full history for a specific URL
SeoIndexingLog::forUrl('https://example.com/page')->latest()->get();

// All URL_DELETED submissions
SeoIndexingLog::forAction('URL_DELETED')->get();

// Successful Bing submissions
SeoIndexingLog::successful()->forEngine('indexnow:www.bing.com')->get();
```

### Log table columns

| Column | Description |
|---|---|
| `url` | The submitted URL |
| `action` | `URL_UPDATED` or `URL_DELETED` |
| `engine` | `google`, `indexnow:www.bing.com`, etc. |
| `success` | Boolean result |
| `http_status` | HTTP response code from the engine |
| `message` | Error message on failure |
| `payload` | Raw JSON response from the API |
| `indexable_type` | Model class that triggered the submission |
| `indexable_id` | Model primary key |
| `job_id` | UUID linking the log entry to its queue job |
| `queued` | Whether this was dispatched via a job |

---

## Architecture & Design Decisions

### Dual-client architecture

Each engine (`GoogleIndexingClient`, `IndexNowClient`) implements the same `IndexingClientContract` interface. They are bound independently in the service container, which means:

- They can be mocked independently in tests
- A failure or misconfiguration in one engine does not affect the other
- New engines can be added by implementing the contract and registering in the service provider

### One job per engine

`SubmitUrlJob` accepts an `$engine` parameter and is dispatched separately for each enabled engine. This isolation means a Google quota error doesn't prevent Bing from receiving the submission, and each engine has its own entry in `failed_jobs` for independent retry tracking.

### Google OAuth2 token handling

The package uses `google/auth` (Google's official PHP auth library) rather than the heavier `google/apiclient`. `ServiceAccountCredentials` reads the JSON key file, signs a JWT, exchanges it for a Bearer token, and caches it for its 1-hour lifetime — all internally. This keeps the dependency footprint small while handling the full OAuth2 service account flow correctly.

### IndexNow native batching

Unlike Google (which requires one HTTP request per URL), IndexNow supports up to 10,000 URLs in a single POST. The `IndexNowClient::submitBatch()` method takes full advantage of this — a batch of 500 URLs becomes 3 HTTP requests (one per engine endpoint) instead of 1,500.

### Retry strategy

The HTTP client retries on 5xx and connection errors but **not** on 4xx errors. A `403 Forbidden` from Google means the credentials are wrong — retrying with the same credentials will always fail and wastes quota. The job layer adds a second retry tier at a higher level for transient failures that survive HTTP retries.

### Deduplication guard

Before dispatching any job, the manager checks whether the same URL was successfully submitted to the same engine within the last 60 minutes. This prevents quota exhaustion during rapid successive saves (e.g. autosave, touch, or event chains on the same model).

### Type+ID serialization

The job stores `indexable_type` and `indexable_id` rather than the Eloquent model instance. Serializing a full model (with its relations) into a queue payload creates large payloads and risks stale data by the time the job runs. Storing the class and key keeps the payload minimal and always fetches a fresh model on execution.

---

## API Quotas & Limits

Be aware of the following limits when planning your usage:

### Google Indexing API

| Limit | Value |
|---|---|
| Requests per day | 200 per service account |
| Requests per minute | 600 |
| Supported URL types | Job posting and livestream pages only (officially) |

> **Note:** Google officially supports the Indexing API only for job posting and livestream structured data pages. Many developers use it for general pages successfully, but this is not officially guaranteed.

### IndexNow

| Limit | Value |
|---|---|
| URLs per batch | Up to 10,000 |
| Daily limit | No hard limit published (10,000+ documented) |
| Engines notified | All IndexNow-compatible engines share submissions |

---

## Testing

The package uses [PHPUnit](https://phpunit.de/) with [Orchestra Testbench](https://github.com/orchestral/testbench).

Run the test suite:

```bash
composer test
# or directly
vendor/bin/phpunit
```

### Mocking in your application

Both clients are bound as singletons and can be swapped in tests:

```php
use DancingJanissary\SeoIndexing\Clients\GoogleIndexingClient;
use DancingJanissary\SeoIndexing\Data\IndexingResult;

// Mock the Google client in a feature test
$this->mock(GoogleIndexingClient::class)
    ->shouldReceive('submit')
    ->once()
    ->with('https://example.com/page', 'URL_UPDATED')
    ->andReturn(IndexingResult::success(
        engine:     'google',
        url:        'https://example.com/page',
        action:     'URL_UPDATED',
        httpStatus: 200,
    ));

// Now trigger the model event
Page::factory()->create(['slug' => 'page', 'status' => 'published']);
```

### Disabling indexing in tests

Add this to your `TestCase` base class to disable all API submissions during the test suite:

```php
protected function setUp(): void
{
    parent::setUp();

    // Disable all indexing submissions in tests
    config(['seo-indexing.engines' => ['google' => false, 'indexnow' => false]]);
}
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

---

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.