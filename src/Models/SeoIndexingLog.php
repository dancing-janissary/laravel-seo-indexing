<?php

namespace DancingJanissary\SeoIndexing\Models;

use DancingJanissary\SeoIndexing\Data\IndexingResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Prunable;

class SeoIndexingLog extends Model
{
    use Prunable;

    protected $table = 'seo_indexing_logs';

    protected $fillable = [
        'url',
        'action',
        'engine',
        'success',
        'http_status',
        'message',
        'payload',
        'indexable_type',
        'indexable_id',
        'job_id',
        'queued',
    ];

    protected $casts = [
        'success' => 'boolean',
        'queued'  => 'boolean',
        'payload' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The model that triggered this indexing submission.
     * Polymorphic so any model using the Indexable trait works.
     */
    public function indexable(): MorphTo
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | Factory method — create a log entry from an IndexingResult
    |--------------------------------------------------------------------------
    */
    public static function fromResult(
        IndexingResult $result,
        ?Model         $indexable = null,
        ?string        $jobId     = null,
        bool           $queued    = false,
    ): self {
        $log = new self($result->toArray());

        $log->queued = $queued;
        $log->job_id = $jobId;

        if ($indexable) {
            $log->indexable_type = get_class($indexable);
            $log->indexable_id   = $indexable->getKey();
        }

        $log->save();

        return $log;
    }

    /*
    |--------------------------------------------------------------------------
    | Prunable — auto-delete old logs based on retention config
    |--------------------------------------------------------------------------
    | Laravel's model pruning runs via: php artisan model:prune
    | Schedule it in your app's console kernel.
    */
    public function prunable(): Builder
    {
        $days = config('seo-indexing.logging.retention_days', 30);

        if ($days === 0) {
            // Retention disabled — never prune
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()
            ->where('created_at', '<=', now()->subDays($days));
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('success', true);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('success', false);
    }

    public function scopeForEngine(Builder $query, string $engine): Builder
    {
        return $query->where('engine', 'like', "{$engine}%");
    }

    public function scopeForUrl(Builder $query, string $url): Builder
    {
        return $query->where('url', $url);
    }

    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}