---
name: seo-indexing-multilang
description: Use when a model has multiple locale-prefixed URLs (e.g. /en/page, /fr/page) that all need to be submitted to search engines when the model changes. Triggers on "multi-language seo indexing", "submit all locales", "locale-prefixed urls".
---

# SEO Indexing — Multi-Language Routes

When a single model renders at multiple locale URLs (e.g. `/en/my-page`, `/fr/my-page`, `/de/my-page`), submit all variants together with `getIndexableUrls()`.

## When to use this

- The app uses locale-prefixed routes (`/en/...`, `/fr/...`)
- A single `Page` record is rendered in multiple languages
- You want all locale URLs re-indexed whenever the underlying model changes

## Single vs. multiple URLs

| Return from model | Behavior |
|---|---|
| `getIndexableUrl()` (single URL) | Submits one URL per save/delete |
| `getIndexableUrls()` returning `null` | Falls back to `getIndexableUrl()` (backward compatible) |
| `getIndexableUrls()` returning `[]` (empty) | Falls back to `getIndexableUrl()` |
| `getIndexableUrls()` returning array of URLs | Submits ALL as a batch via `submitBatch()` |

## Implementation

```php
use DancingJanissary\SeoIndexing\Traits\Indexable;

class Page extends Model
{
    use Indexable;

    public function getIndexableUrls(): ?array
    {
        $locales = ['en', 'fr', 'de'];

        return collect($locales)
            ->mapWithKeys(fn ($locale) => [
                $locale => route('pages.show', [
                    'locale' => $locale,
                    'slug'   => $this->slug,
                ]),
            ])
            ->all();
    }
}
```

Produces this batch on every save:
```
https://example.com/en/my-page
https://example.com/fr/my-page
https://example.com/de/my-page
```

## Config source for locales

Avoid hardcoding locales in the model. Pull from config or a translation table:

```php
public function getIndexableUrls(): ?array
{
    return collect(config('app.supported_locales'))
        ->mapWithKeys(fn ($locale) => [
            $locale => route('pages.show', ['locale' => $locale, 'slug' => $this->slug]),
        ])
        ->all();
}
```

If you use `spatie/laravel-translatable` and a page only has translations for some locales:

```php
public function getIndexableUrls(): ?array
{
    $translatedLocales = array_keys($this->getTranslations('title'));

    return collect($translatedLocales)
        ->mapWithKeys(fn ($locale) => [
            $locale => route('pages.show', ['locale' => $locale, 'slug' => $this->getTranslation('slug', $locale)]),
        ])
        ->all();
}
```

## What search engines actually do with these

- **Google Indexing API** and **IndexNow** accept URLs only — no hreflang metadata.
- To associate locale variants, your HTML must include `<link rel="alternate" hreflang="...">` tags on each page.
- Submitting the URLs tells the engines *"crawl these pages now"* — they pick up hreflang relationships during the crawl.

Example hreflang tags in your Blade layout:

```blade
@foreach(config('app.supported_locales') as $locale)
    <link rel="alternate" hreflang="{{ $locale }}"
          href="{{ route('pages.show', ['locale' => $locale, 'slug' => $page->getTranslation('slug', $locale)]) }}">
@endforeach
<link rel="alternate" hreflang="x-default"
      href="{{ route('pages.show', ['locale' => 'en', 'slug' => $page->getTranslation('slug', 'en')]) }}">
```

## Batching behavior

- `IndexNowClient` sends one HTTP request per configured engine (Bing, Yandex, api.indexnow.org) with all URLs in the body — up to 10,000 per batch.
- `GoogleIndexingClient` sends one HTTP request **per URL** (Google has no batch endpoint). For 3 locales this is 3 Google requests + 3 IndexNow engine requests.
- Quotas apply to Google: 200 URLs/day per service account. With 3 locales that's ~66 models/day before hitting the limit.

## Quota-saving tactic for many locales

If you have 20+ locales, consider submitting to Google only for the default locale, and IndexNow for all:

```php
public function getIndexableUrls(): ?array
{
    // All locales go to IndexNow (high quota)
    return collect(config('app.supported_locales'))
        ->mapWithKeys(fn ($locale) => [
            $locale => route('pages.show', ['locale' => $locale, 'slug' => $this->slug]),
        ])
        ->all();
}

// Then in config/seo-indexing.php, keep Google enabled but selectively submit
// via the facade: SeoIndexing::submit($defaultUrl, forGoogleOnly: true)
```

(Note: current version submits to all enabled engines uniformly — split via facade if you need fine-grained control.)

## Testing multi-language submission

```php
use Illuminate\Support\Facades\Queue;
use DancingJanissary\SeoIndexing\Jobs\SubmitUrlJob;

test('saving a page dispatches one job per enabled engine with all locale urls', function () {
    Queue::fake();

    Page::factory()->create(['slug' => 'hello', 'status' => 'published']);

    Queue::assertPushed(SubmitUrlJob::class, function ($job) {
        return count($job->urls ?? []) === 3  // en, fr, de
            && $job->isBatch === true;
    });
});
```
