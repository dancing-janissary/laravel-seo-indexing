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
                app(SeoIndexingManager::class)->submit(
                    url:       $model->getIndexableUrl(),
                    indexable: $model,
                );
            }
        });

        /*
        | deleted → URL_DELETED
        | Fires on both hard delete and soft delete.
        */
        static::deleted(function (Model $model) {
            if ($model->shouldIndex()) {
                app(SeoIndexingManager::class)->delete(
                    url:       $model->getIndexableUrl(),
                    indexable: $model,
                );
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
                    app(SeoIndexingManager::class)->submit(
                        url:       $model->getIndexableUrl(),
                        indexable: $model,
                    );
                }
            });
        }
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
        $manager = app(SeoIndexingManager::class);

        match ($action) {
            SeoIndexingManager::ACTION_UPDATED => $manager->submit($this->getIndexableUrl(), $this),
            SeoIndexingManager::ACTION_DELETED => $manager->delete($this->getIndexableUrl(), $this),
            default => throw new \InvalidArgumentException(
                "Invalid indexing action: [{$action}]. " .
                "Use SeoIndexingManager::ACTION_UPDATED or ACTION_DELETED."
            ),
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