---
name: seo-indexing-integration
description: Use when adding the Indexable trait to an Eloquent model, configuring which URLs get submitted, or wiring a model into SEO indexing automatically. Triggers on "add seo indexing to model", "make model indexable", "wire model to google indexing".
---

# SEO Indexing — Model Integration

Attach the `Indexable` trait to any Eloquent model whose public URLs should be submitted to Google and IndexNow on `created`, `updated`, `deleted`, or `restored` events.

## Minimal setup

```php
use DancingJanissary\SeoIndexing\Traits\Indexable;

class Page extends Model
{
    use Indexable;
}
```

Default behavior: builds URL as `{APP_URL}/{slug}` using the model's `slug` attribute (falls back to primary key if `slug` is missing).

## Event mapping

| Eloquent Event | Action dispatched |
|---|---|
| `created` / `updated` | `URL_UPDATED` |
| `deleted` | `URL_DELETED` |
| `restored` (SoftDeletes) | `URL_UPDATED` |

## Override the URL

**Option A — explicit URL via `getIndexableUrl()` (most flexible):**

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

**Option B — URL prefix using default slug-based builder:**

```php
protected function getIndexablePrefix(): string
{
    return '/blog';  // produces https://example.com/blog/{slug}
}
```

## Gate which pages get indexed

Override `shouldIndex()` — return `false` to skip the submission entirely (no job, no log):

```php
public function shouldIndex(): bool
{
    return parent::shouldIndex()
        && $this->status === 'published'
        && ! $this->is_private;
}
```

Common gates: `status === 'published'`, `published_at <= now()`, `noindex` flag absent, model is not soft-deleted.

## Disable indexing during bulk operations

Bulk imports can exhaust Google's 200/day quota. Disable indexing first:

**Option A — static disable/enable:**
```php
Page::disableIndexing();
foreach ($importRows as $row) Page::create($row);
Page::enableIndexing();
```

**Option B — closure (auto re-enables, exception-safe):**
```php
$page->withoutIndexing(function () use ($page) {
    $page->update(['status' => 'draft']);
});
```

## Trigger submission manually on a model instance

```php
use DancingJanissary\SeoIndexing\SeoIndexingManager;

$page->index();                                       // submits URL_UPDATED
$page->index(SeoIndexingManager::ACTION_DELETED);     // submits URL_DELETED
```

Useful for: admin re-submit buttons, seeders that need explicit control, content migrations where events are suppressed.

## Common pitfalls

1. **Model uses soft-deletes but `getIndexableUrl()` hits the DB** — on `deleted` event the row still exists in the DB, but on `forceDelete` attempting to resolve related records inside `getIndexableUrl()` will fail. Prefer building the URL from attributes on `$this` directly rather than traversing relations.

2. **`shouldIndex()` called on unsaved models** — called during `saved`/`deleted` events, so the model is persisted. But if you call `$page->index()` on a fresh `new Page()` with no primary key, the URL will likely be broken.

3. **Forgetting to check `shouldIndex` in tests** — if a factory creates a draft page but `shouldIndex()` requires `status === 'published'`, no job is dispatched. Tests expecting a job fail silently.

4. **Events suppressed by `saveQuietly()` or `withoutEvents()`** — indexing is event-based. Quiet saves will NOT trigger indexing. Call `$model->index()` manually if needed.

## Add to an existing model — checklist

- [ ] `use Indexable;` added to the model
- [ ] `getIndexableUrl()` returns a valid public URL (override if not `{APP_URL}/{slug}`)
- [ ] `shouldIndex()` returns `false` for drafts / private / unpublished rows
- [ ] No private URLs leak (admin routes, preview links)
- [ ] Tested that a save triggers a job (check `jobs` or `failed_jobs` table)
- [ ] Log entry appears in `seo_indexing_logs` with expected URL and `indexable_type`/`indexable_id`

## Testing a newly indexable model

```php
// tests/Feature/PageIndexingTest.php
use Illuminate\Support\Facades\Queue;
use DancingJanissary\SeoIndexing\Jobs\SubmitUrlJob;

test('published pages dispatch an indexing job on create', function () {
    Queue::fake();
    Page::factory()->create(['status' => 'published', 'slug' => 'hello']);
    Queue::assertPushed(SubmitUrlJob::class);
});

test('draft pages do not dispatch a job', function () {
    Queue::fake();
    Page::factory()->create(['status' => 'draft']);
    Queue::assertNothingPushed();
});
```
