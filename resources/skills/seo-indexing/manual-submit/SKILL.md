---
name: seo-indexing-manual-submit
description: Use when submitting URLs manually via the SeoIndexing facade (not tied to a model event) — controllers, console commands, observers, resubmit actions, batch re-indexing. Triggers on "manually submit url", "seo indexing facade", "batch index urls", "force reindex".
---

# SEO Indexing — Manual Submission via Facade

Use the `SeoIndexing` facade when the source of submission isn't an Eloquent event — e.g. a controller re-index button, a console command, a scheduled re-submission, or pages not backed by a model.

## Single URL

```php
use DancingJanissary\SeoIndexing\Facades\SeoIndexing;

// Tell search engines a URL was created/updated
SeoIndexing::submit('https://example.com/page');

// Tell search engines a URL was removed
SeoIndexing::delete('https://example.com/old-page');
```

Under the hood these dispatch `SubmitUrlJob`s respecting the `queue.enabled` config. Each enabled engine gets its own job.

## Batch submission

IndexNow supports up to 10,000 URLs per batch request. Google splits into one request per URL internally but you still use the same batch API:

```php
SeoIndexing::submitBatch([
    'https://example.com/page-1',
    'https://example.com/page-2',
    'https://example.com/page-3',
]);

SeoIndexing::deleteBatch([
    'https://example.com/removed-1',
    'https://example.com/removed-2',
]);
```

## Common use cases

### 1. Re-submit action in an admin panel

```php
// app/Http/Controllers/Admin/PageController.php
public function resubmit(Page $page)
{
    $page->index();  // uses the Indexable trait
    return back()->with('success', "Submitted {$page->slug} to search engines.");
}
```

### 2. Scheduled re-submission of stale URLs

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;
use DancingJanissary\SeoIndexing\Models\SeoIndexingLog;
use DancingJanissary\SeoIndexing\Facades\SeoIndexing;

Schedule::call(function () {
    $stale = SeoIndexingLog::successful()
        ->where('created_at', '<', now()->subDays(30))
        ->distinct('url')
        ->pluck('url')
        ->all();

    foreach (array_chunk($stale, 1000) as $chunk) {
        SeoIndexing::submitBatch($chunk);
    }
})->weekly();
```

### 3. Artisan command for ad-hoc submission

```php
// app/Console/Commands/IndexUrl.php
use Illuminate\Console\Command;
use DancingJanissary\SeoIndexing\Facades\SeoIndexing;

class IndexUrl extends Command
{
    protected $signature = 'seo:index {url} {--delete}';
    protected $description = 'Submit a URL to Google and IndexNow';

    public function handle()
    {
        $url = $this->argument('url');
        $this->option('delete')
            ? SeoIndexing::delete($url)
            : SeoIndexing::submit($url);
        $this->info("Submitted: {$url}");
    }
}
```

Run: `php artisan seo:index https://example.com/page`

### 4. Sitemap re-submission after a large content migration

```php
// Extract all URLs from your sitemap and re-submit in batches
$urls = /* your sitemap parser */;
foreach (array_chunk($urls, 1000) as $chunk) {
    SeoIndexing::submitBatch($chunk);
}
```

## Deduplication guard

Before dispatching any job, the manager checks whether the same URL+engine combination was submitted successfully within the last **60 minutes**. This prevents quota exhaustion during rapid consecutive saves.

**Implication:** calling `submit()` twice in a row on the same URL only produces one network submission. If you need to force a re-submission (e.g. after a visible content change), wait 60 minutes or clear relevant `seo_indexing_logs` entries.

## Querying the log

```php
use DancingJanissary\SeoIndexing\Models\SeoIndexingLog;

// All failed submissions in last 7 days
SeoIndexingLog::failed()->recent(7)->get();

// Full history for a URL
SeoIndexingLog::forUrl('https://example.com/page')->latest()->get();

// Google failures specifically
SeoIndexingLog::failed()->forEngine('google')->latest()->get();

// Successful Bing submissions
SeoIndexingLog::successful()->forEngine('indexnow:www.bing.com')->get();
```

Available scopes: `successful()`, `failed()`, `forUrl($url)`, `forAction($action)`, `forEngine($engine)`, `recent($days)`.

## Action constants

```php
use DancingJanissary\SeoIndexing\SeoIndexingManager;

SeoIndexingManager::ACTION_UPDATED  // 'URL_UPDATED'
SeoIndexingManager::ACTION_DELETED  // 'URL_DELETED'
```

## Sync vs queue mode

- `SEO_INDEXING_QUEUE_ENABLED=true` (default) — `submit()` returns immediately; job handles HTTP.
- `SEO_INDEXING_QUEUE_ENABLED=false` — `submit()` blocks until the HTTP request completes. Use only in local dev or simple CRON-driven scripts.

## Mocking in tests

Both engine clients are singletons — swap with a mock to avoid real HTTP:

```php
use DancingJanissary\SeoIndexing\Clients\GoogleIndexingClient;
use DancingJanissary\SeoIndexing\Data\IndexingResult;

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
```

Or disable all engines in the test suite base class:

```php
protected function setUp(): void
{
    parent::setUp();
    config(['seo-indexing.engines' => ['google' => false, 'indexnow' => false]]);
}
```
