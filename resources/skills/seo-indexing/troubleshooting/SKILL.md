---
name: seo-indexing-troubleshooting
description: Use when diagnosing why URLs aren't being submitted to Google or IndexNow — failed jobs, empty logs, 403/401 errors, quota issues, no dispatch on save. Triggers on "seo indexing not working", "google indexing 403", "indexnow failing", "why no indexing job", "seo debugging".
---

# SEO Indexing — Troubleshooting

Diagnose why `dancing-janissary/laravel-seo-indexing` isn't submitting URLs as expected. Work through symptoms top-to-bottom.

## Diagnostic flow

```
1. Is a job being dispatched?         → check jobs table / Queue::fake()
2. Is the job succeeding?              → check failed_jobs + seo_indexing_logs
3. Is the HTTP request being made?     → check seo_indexing_logs http_status
4. Is the response success?            → check seo_indexing_logs success + message
```

## Symptom: No job dispatched on save

**Check 1 — Trait applied?**
```php
class_uses(Page::class)  // must include Indexable trait
```

**Check 2 — `shouldIndex()` returning false?**

Override may be too restrictive. Log the value temporarily:
```php
public function shouldIndex(): bool
{
    $ok = parent::shouldIndex() && $this->status === 'published';
    logger()->info('shouldIndex', ['model' => $this->id, 'ok' => $ok]);
    return $ok;
}
```

**Check 3 — Events suppressed?**

`saveQuietly()`, `updateQuietly()`, `Model::withoutEvents()`, or `disableIndexing()` all block dispatch. Search recent code:
```bash
grep -rn "saveQuietly\|withoutEvents\|disableIndexing" app/
```

**Check 4 — Deduplication guard hit?**

The manager skips dispatch if the same URL+engine was successfully submitted in the last 60 minutes. Check:
```php
SeoIndexingLog::forUrl($url)->recent(1)->successful()->exists();
```
If `true`, that's why no new job was dispatched. This is expected behavior.

## Symptom: Job dispatched but failing

**Check `failed_jobs` first:**
```bash
php artisan queue:failed
# or
SELECT exception FROM failed_jobs WHERE queue = 'indexing' ORDER BY failed_at DESC LIMIT 5;
```

**Check `seo_indexing_logs` for the specific error:**
```php
use DancingJanissary\SeoIndexing\Models\SeoIndexingLog;

SeoIndexingLog::failed()->recent(1)->latest()->limit(10)->get()
    ->each(fn ($l) => dump([
        'url'    => $l->url,
        'engine' => $l->engine,
        'status' => $l->http_status,
        'msg'    => $l->message,
    ]));
```

## Common HTTP status codes

### Google — `403 Forbidden` / `permission denied`
- Service account isn't added as **Owner** in Google Search Console
- Property URL mismatch — credentials must own the domain being indexed
- Indexing API not enabled for the Cloud project

**Fix:** Re-check Search Console settings. Go to Search Console → Settings → Users and permissions → add the `xxx@xxx.iam.gserviceaccount.com` email as **Owner** (not Restricted).

### Google — `401 Unauthorized`
- `GOOGLE_INDEXING_CREDENTIALS_PATH` points to wrong file or file is malformed
- Service account key is disabled/deleted in GCP

**Fix:**
```bash
# Validate the JSON file structure
cat $GOOGLE_INDEXING_CREDENTIALS_PATH | jq '.type, .client_email, .private_key | length'
# Expected: "service_account", a valid email, a large private key length (>1600 chars)
```

### Google — `429 Too Many Requests`
- Hit daily quota (200 URLs/day per service account)
- Hit per-minute quota (600/min)

**Fix:** Wait, or request quota increase in GCP console. Use `disableIndexing()` during bulk imports to avoid burning the quota.

### Google — `404 Not Found` on URL
- The URL returns 404 or is blocked by robots.txt
- Page requires auth / is not publicly accessible

**Fix:** Open the URL in an incognito browser. It must return `200` to unauthenticated users. Check robots.txt doesn't block it.

### IndexNow — `403 Forbidden`
- Key verification file not served at `https://your-domain.com/{key}.txt`

**Fix:** Test the verification file:
```bash
curl https://your-domain.com/$INDEXNOW_KEY.txt
# Must return the key itself as plain text, HTTP 200
```

### IndexNow — `422 Unprocessable Entity`
- Host mismatch — submitted URL's host must match `indexnow.host` config
- `APP_URL` in `.env` is wrong (e.g. http vs https, www vs non-www)

**Fix:**
```bash
php artisan tinker
>>> config('seo-indexing.indexnow.host')  # must match your submitted URLs' host
```

## Symptom: No HTTP request happening at all

**Check 1 — Queue worker running?**
```bash
ps aux | grep 'queue:work'
# Must show a worker for 'indexing' queue or 'default'
```
If queue mode is enabled but no worker is processing, jobs pile up in the `jobs` table and nothing reaches Google/Bing.

**Check 2 — Correct queue name?**
```php
config('seo-indexing.queue.name')          // what the package dispatches to
config('seo-indexing.queue.connection')    // which driver (redis/database/sqs)
```
Your worker must be processing THAT queue: `queue:work {connection} --queue={name}`.

**Check 3 — Engines disabled in config?**
```php
config('seo-indexing.engines')
// ['google' => true, 'indexnow' => true]  ← both should be true
```

## Symptom: Submissions work locally but not in production

- Different `APP_URL` → `IndexNowClient` uses it for the `host` field
- Service account credentials file path invalid on prod server — use an absolute path, NOT relative
- Queue driver mismatch — `.env` locally uses `sync`, prod uses `redis` with no worker
- `SEO_INDEXING_QUEUE_ENABLED=false` locally but `true` in prod with no worker

## Symptom: `seo_indexing_logs` table is empty

- Migration wasn't run: `php artisan migrate`
- `logging.enabled` is `false` in `config/seo-indexing.php`
- Log retention pruned entries: `SEO_INDEXING_LOG_RETENTION=30` + `model:prune` scheduler

## Quick verification script

Paste in `php artisan tinker`:

```php
// 1. Services resolve?
app('seo-indexing');  // should return SeoIndexingManager

// 2. Config present?
config('seo-indexing.google.credentials_path');  // not null
config('seo-indexing.indexnow.key');              // not null

// 3. Credentials file readable?
file_exists(config('seo-indexing.google.credentials_path'));  // true

// 4. DB table exists?
Schema::hasTable('seo_indexing_logs');  // true

// 5. Fire a test submission
use DancingJanissary\SeoIndexing\Facades\SeoIndexing;
SeoIndexing::submit(config('app.url') . '/test');

// 6. Inspect latest log entries
DancingJanissary\SeoIndexing\Models\SeoIndexingLog::latest()->limit(5)->get();
```

## When all else fails

1. Temporarily set `SEO_INDEXING_QUEUE_ENABLED=false` — makes submissions synchronous so errors surface in the request, not in failed jobs
2. Wrap a call in try/catch to see the raw exception:
```php
try {
    SeoIndexing::submit('https://example.com/test');
} catch (\Throwable $e) {
    dd($e->getMessage(), $e->getTrace());
}
```
3. Enable Laravel HTTP logging to see exact request/response bodies:
```php
use Illuminate\Support\Facades\Http;
Http::globalMiddleware(function ($req, $handler) {
    // log $req and await response — inspect during dev only
});
```
