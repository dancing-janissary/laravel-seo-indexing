<?php

// src/Traits/Indexable.php

namespace DancingJanissary\SeoIndexing\Traits;

use DancingJanissary\SeoIndexing\SeoIndexingManager;
use Illuminate\Database\Eloquent\Model;

trait Indexable
{
    /*
    |--------------------------------------------------------------------------
    | Boot the trait — register model event listeners
    |--------------------------------------------------------------------------
    | bootIndexable() is called automatically by Laravel when the model
    | boots, following the convention: boot{TraitName}()
    */
    public static function bootIndexable(): void
    {
        /*
        | created & updated → URL_UPDATED
        | We use 'saved' instead of separate 'created'+'updated' listeners
        | to avoid double-firing on first save of a new model.
        */
        static::saved(function (Model $model) {
            if ($model->shouldIndex()) {
                $model->dispatchIndexingUrls(SeoIndexingManager::ACTION_UPDATED);
            }
        });

        /*
        | deleted → URL_DELETED
        | Fires on both hard delete and soft delete.
        */
        static::deleted(function (Model $model) {
            if ($model->shouldIndex()) {
                $model->dispatchIndexingUrls(SeoIndexingManager::ACTION_DELETED);
            }
        });

        /*
        | restored (SoftDeletes) → URL_UPDATED
        | When a soft-deleted model is restored, notify engines
        | the URL is live again.
        */
        if (static::hasRestoreEvent()) {
            static::restored(function (Model $model) {
                if ($model->shouldIndex()) {
                    $model->dispatchIndexingUrls(SeoIndexingManager::ACTION_UPDATED);
                }
            });
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get all locale-specific URLs for this model (multi-language support)
    |--------------------------------------------------------------------------
    | Override this in your model to return URLs for each locale.
    | Returns null by default, which falls back to getIndexableUrl().
    |
    | Example override:
    |
    |   public function getIndexableUrls(): ?array
    |   {
    |       return collect(['en', 'fr', 'de'])->mapWithKeys(fn ($locale) => [
    |           $locale => route('pages.show', ['locale' => $locale, 'slug' => $this->slug]),
    |       ])->all();
    |   }
    */
    public function getIndexableUrls(): ?array
    {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve the URLs to submit (single or multi-locale)
    |--------------------------------------------------------------------------
    | Returns an array of one or more URLs. If getIndexableUrls() returns
    | a non-empty array, those URLs are used. Otherwise falls back to
    | the single URL from getIndexableUrl().
    */
    protected function resolveIndexableUrls(): array
    {
        $urls = $this->getIndexableUrls();

        if ($urls !== null && count($urls) > 0) {
            return array_values($urls);
        }

        return [$this->getIndexableUrl()];
    }

    /*
    |--------------------------------------------------------------------------
    | Get the URL to submit to indexing engines
    |--------------------------------------------------------------------------
    | Override this in your model to return the correct public URL.
    | Default implementation assumes the model has a 'slug' attribute
    | and falls back to the model's key.
    |
    | Example override:
    |
    |   public function getIndexableUrl(): string
    |   {
    |       return route('pages.show', $this->slug);
    |   }
    */
    public function getIndexableUrl(): string
    {
        if (isset($this->attributes['slug'])) {
            return url($this->getIndexablePrefix() . '/' . $this->slug);
        }

        return url($this->getIndexablePrefix() . '/' . $this->getKey());
    }

    /*
    |--------------------------------------------------------------------------
    | URL prefix for the default getIndexableUrl() implementation
    |--------------------------------------------------------------------------
    | Override to set the base path for this model's URLs.
    |
    | Example:
    |   protected function getIndexablePrefix(): string
    |   {
    |       return '/blog';
    |   }
    */
    protected function getIndexablePrefix(): string
    {
        return '';
    }

    /*
    |--------------------------------------------------------------------------
    | Control whether this specific model instance should be indexed
    |--------------------------------------------------------------------------
    | Override to add conditions — e.g. only index published pages:
    |
    |   public function shouldIndex(): bool
    |   {
    |       return $this->status === 'published';
    |   }
    */
    public function shouldIndex(): bool
    {
        return static::isIndexingEnabled();
    }

    /*
    |--------------------------------------------------------------------------
    | Manually trigger indexing outside of model events
    |--------------------------------------------------------------------------
    | Useful for bulk operations or re-indexing from a command.
    |
    | Usage:
    |   $page->index();              // submit as updated
    |   $page->index('URL_DELETED'); // submit as deleted
    */
    public function index(string $action = SeoIndexingManager::ACTION_UPDATED): void
    {
        if (! in_array($action, [SeoIndexingManager::ACTION_UPDATED, SeoIndexingManager::ACTION_DELETED], true)) {
            throw new \InvalidArgumentException(
                "Invalid indexing action: [{$action}]. " .
                "Use SeoIndexingManager::ACTION_UPDATED or ACTION_DELETED."
            );
        }

        $this->dispatchIndexingUrls($action);
    }

    /*
    |--------------------------------------------------------------------------
    | Dispatch resolved URLs to the indexing manager
    |--------------------------------------------------------------------------
    | Routes through submit/delete for single URLs (preserves per-URL dedup)
    | or submitBatch/deleteBatch for multiple locale URLs.
    */
    protected function dispatchIndexingUrls(string $action): void
    {
        $manager = app(SeoIndexingManager::class);
        $urls = $this->resolveIndexableUrls();

        if (count($urls) === 1) {
            match ($action) {
                SeoIndexingManager::ACTION_UPDATED => $manager->submit($urls[0], $this),
                SeoIndexingManager::ACTION_DELETED => $manager->delete($urls[0], $this),
            };
            return;
        }

        match ($action) {
            SeoIndexingManager::ACTION_UPDATED => $manager->submitBatch($urls, $this),
            SeoIndexingManager::ACTION_DELETED => $manager->deleteBatch($urls, $this),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Temporarily disable indexing for this model instance
    |--------------------------------------------------------------------------
    | Usage:
    |   $page->withoutIndexing(function () use ($page) {
    |       $page->update(['title' => 'Draft']);
    |   });
    */
    public function withoutIndexing(callable $callback): mixed
    {
        static::disableIndexing();

        try {
            return $callback();
        } finally {
            static::enableIndexing();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Class-level indexing toggle
    |--------------------------------------------------------------------------
    | Disable indexing for bulk imports:
    |
    |   Page::disableIndexing();
    |   // import 1000 pages...
    |   Page::enableIndexing();
    */
    public static function disableIndexing(): void
    {
        static::$indexingEnabled = false;
    }

    public static function enableIndexing(): void
    {
        static::$indexingEnabled = true;
    }

    public static function isIndexingEnabled(): bool
    {
        return static::$indexingEnabled ?? true;
    }

    /*
    |--------------------------------------------------------------------------
    | Internal toggle state (per model class)
    |--------------------------------------------------------------------------
    */
    protected static bool $indexingEnabled = true;

    /*
    |--------------------------------------------------------------------------
    | Check whether the model class supports SoftDeletes restore event
    |--------------------------------------------------------------------------
    */
    protected static function hasRestoreEvent(): bool
    {
        return method_exists(static::class, 'restore');
    }
}