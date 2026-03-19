<?php

// src/Contracts/IndexingClientContract.php

namespace DancingJanissary\SeoIndexing\Contracts;

use DancingJanissary\SeoIndexing\Data\IndexingResult;

interface IndexingClientContract
{
    /*
    |--------------------------------------------------------------------------
    | Submit a single URL
    |--------------------------------------------------------------------------
    | Action should be one of: 'URL_UPDATED', 'URL_DELETED'
    */
    public function submit(string $url, string $action): IndexingResult;

    /*
    |--------------------------------------------------------------------------
    | Submit multiple URLs at once
    |--------------------------------------------------------------------------
    | Returns an array of IndexingResult, one per URL.
    | Not all engines support native batching — implementations
    | may loop internally but should still return per-URL results.
    */
    public function submitBatch(array $urls, string $action): array;

    /*
    |--------------------------------------------------------------------------
    | Check if client is properly configured
    |--------------------------------------------------------------------------
    | Called before dispatch to fail fast with a clear error rather than
    | a cryptic HTTP response.
    */
    public function isConfigured(): bool;

    /*
    |--------------------------------------------------------------------------
    | Engine identifier
    |--------------------------------------------------------------------------
    | Returns a short string used in logs: 'google', 'indexnow'
    */
    public function getEngine(): string;
}