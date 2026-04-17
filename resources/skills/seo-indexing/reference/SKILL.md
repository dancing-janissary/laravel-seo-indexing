---
name: seo-indexing-reference
description: API reference for dancing-janissary/laravel-seo-indexing — facade methods, model traits, events, config keys, log scopes, action constants. Use when looking up exact method signatures, config paths, or available options.
---

# SEO Indexing — API Reference

Quick lookup for all public APIs and configuration surfaces.

## Facade — `DancingJanissary\SeoIndexing\Facades\SeoIndexing`

| Method | Signature | Purpose |
|---|---|---|
| `submit` | `submit(string $url): void` | Dispatch `URL_UPDATED` for one URL |
| `delete` | `delete(string $url): void` | Dispatch `URL_DELETED` for one URL |
| `submitBatch` | `submitBatch(array $urls): void` | Dispatch `URL_UPDATED` for many URLs |
| `deleteBatch` | `deleteBatch(array $urls): void` | Dispatch `URL_DELETED` for many URLs |

Resolves to `SeoIndexingManager` (container key: `seo-indexing`).

## Trait — `DancingJanissary\SeoIndexing\Traits\Indexable`

### Overridable hooks

| Method | Default | Purpose |
|---|---|---|
| `getIndexableUrl(): string` | `{APP_URL}/{slug or key}` | Returns the single URL for this model |
| `getIndexableUrls(): ?array` | `null` | Returns array of URLs for multi-locale; `null` = single URL mode |
| `getIndexablePrefix(): string` | `''` | URL path prefix when using default slug-based builder |
| `shouldIndex(): bool` | `true` | Gate — return `false` to skip submission |

### Instance methods added by trait

| Method | Purpose |
|---|---|
| `$model->index(?string $action = null)` | Manually trigger submission; defaults to `URL_UPDATED` |
| `$model->withoutIndexing(Closure $fn)` | Run closure with indexing disabled for this model class |

### Static methods added by trait

| Method | Purpose |
|---|---|
| `Model::disableIndexing(): void` | Globally disable indexing for this model class |
| `Model::enableIndexing(): void` | Re-enable indexing for this model class |

## Action constants — `SeoIndexingManager`

```php
SeoIndexingManager::ACTION_UPDATED  // 'URL_UPDATED'
SeoIndexingManager::ACTION_DELETED  // 'URL_DELETED'
```

## Model — `DancingJanissary\SeoIndexing\Models\SeoIndexingLog`

### Table: `seo_indexing_logs`

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `url` | string | Submitted URL |
| `action` | string | `URL_UPDATED` or `URL_DELETED` |
| `engine` | string | `google`, `indexnow:www.bing.com`, `indexnow:api.indexnow.org`, etc. |
| `success` | bool | Whether the API call succeeded |
| `http_status` | int | HTTP response code |
| `message` | text | Error message on failure, null on success |
| `payload` | json | Raw API response body |
| `indexable_type` | string | Model class (morph) — null for facade submissions |
| `indexable_id` | string | Model key (morph) — null for facade submissions |
| `job_id` | uuid | Links log entry to its queue job |
| `queued` | bool | `true` if dispatched via a job, `false` if sync |
| `created_at` / `updated_at` | timestamp | Standard Eloquent timestamps |

### Scopes

| Scope | Purpose |
|---|---|
| `successful()` | `where('success', true)` |
| `failed()` | `where('success', false)` |
| `forUrl(string $url)` | Filter by exact URL |
| `forAction(string $action)` | `URL_UPDATED` or `URL_DELETED` |
| `forEngine(string $engine)` | `google`, `indexnow:www.bing.com`, etc. |
| `recent(int $days)` | `where('created_at', '>=', now()->subDays($days))` |

### Relations

| Relation | Type | Returns |
|---|---|---|
| `indexable()` | `MorphTo` | The Eloquent model that triggered the submission, or `null` for facade calls |

Implements `Illuminate\Database\Eloquent\Prunable` — auto-pruned via `model:prune` Artisan command based on `logging.retention_days` config.

## Service container bindings

| Key / Class | Type | Concrete |
|---|---|---|
| `seo-indexing` | singleton | `SeoIndexingManager` |
| `SeoIndexingManager::class` | singleton | `SeoIndexingManager` |
| `GoogleIndexingClient::class` | singleton | `GoogleIndexingClient` |
| `IndexNowClient::class` | singleton | `IndexNowClient` |
| `IndexingLogger::class` | singleton | `IndexingLogger` |

All swappable via `$this->app->bind(...)` for testing.

## Events fired by the package

None by default. Submissions happen inside `SubmitUrlJob::handle()` — observe by querying `SeoIndexingLog` or by binding a custom logger.

## Config — `config/seo-indexing.php`

```php
return [
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
        'key_file' => env('INDEXNOW_KEY_FILE', null),  // defaults to {key}.txt
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
            'sleep' => 1000,  // ms between retries
        ],
    ],
];
```

## Environment variables

| Variable | Default | Required | Purpose |
|---|---|---|---|
| `GOOGLE_INDEXING_CREDENTIALS_PATH` | null | yes (if Google enabled) | Absolute path to service account JSON |
| `INDEXNOW_KEY` | null | yes (if IndexNow enabled) | API key, alphanumeric 8+ chars |
| `INDEXNOW_KEY_FILE` | `{key}.txt` | no | Override the verification filename |
| `APP_URL` | — | yes | Base URL used as IndexNow `host` and default URL builder |
| `SEO_INDEXING_QUEUE_ENABLED` | `true` | no | Dispatch as jobs (recommended) |
| `SEO_INDEXING_QUEUE_CONNECTION` | `default` | no | Queue connection name |
| `SEO_INDEXING_QUEUE_NAME` | `indexing` | no | Queue name |
| `SEO_INDEXING_LOG_RETENTION` | `30` | no | Days; 0 = keep forever |

## Jobs — `DancingJanissary\SeoIndexing\Jobs\SubmitUrlJob`

| Property | Value | Purpose |
|---|---|---|
| `$tries` | `2` | Max attempts |
| `$timeout` | `60` | Seconds before job times out |
| Queue connection | `config('seo-indexing.queue.connection')` | |
| Queue name | `config('seo-indexing.queue.name')` | |

## Publishable tags

| Tag | Publishes |
|---|---|
| `seo-indexing` | Config + migrations |
| `seo-indexing-config` | Config only |
| `seo-indexing-migrations` | Migrations only |
| `seo-indexing-skills` | AI agent skill files to `.claude/skills/seo-indexing/` |

## Quotas and limits

| Engine | Limit |
|---|---|
| Google Indexing API | 200 requests/day, 600/minute per service account |
| IndexNow | Up to 10,000 URLs per batch request, no hard daily cap documented |
