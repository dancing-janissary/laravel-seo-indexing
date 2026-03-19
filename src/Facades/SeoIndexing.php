<?php

// src/Facades/SeoIndexing.php

namespace DancingJanissary\SeoIndexing\Facades;

use DancingJanissary\SeoIndexing\SeoIndexingManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void submit(string $url, ?\Illuminate\Database\Eloquent\Model $indexable = null)
 * @method static void delete(string $url, ?\Illuminate\Database\Eloquent\Model $indexable = null)
 * @method static void submitBatch(array $urls, ?\Illuminate\Database\Eloquent\Model $indexable = null)
 * @method static void deleteBatch(array $urls, ?\Illuminate\Database\Eloquent\Model $indexable = null)
 *
 * @see \DancingJanissary\SeoIndexing\SeoIndexingManager
 */
class SeoIndexing extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'seo-indexing';
    }
}