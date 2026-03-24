<?php

namespace DancingJanissary\SeoIndexing\Tests\Unit;

use DancingJanissary\SeoIndexing\Data\IndexingResult;
use DancingJanissary\SeoIndexing\Models\SeoIndexingLog;
use DancingJanissary\SeoIndexing\Tests\TestCase;

class SeoIndexingLogTest extends TestCase
{
    public function test_from_result_creates_record(): void
    {
        $result = IndexingResult::success(
            engine: 'google',
            url: 'https://example.com/page',
            action: 'URL_UPDATED',
            httpStatus: 200,
            payload: ['url' => 'https://example.com/page'],
        );

        $log = SeoIndexingLog::fromResult($result, jobId: 'job-123', queued: true);

        $this->assertDatabaseHas('seo_indexing_logs', [
            'url'         => 'https://example.com/page',
            'engine'      => 'google',
            'action'      => 'URL_UPDATED',
            'success'     => true,
            'http_status' => 200,
            'job_id'      => 'job-123',
            'queued'      => true,
        ]);
        $this->assertSame(['url' => 'https://example.com/page'], $log->payload);
    }

    public function test_from_result_with_failure(): void
    {
        $result = IndexingResult::failure(
            engine: 'indexnow',
            url: 'https://example.com/page',
            action: 'URL_DELETED',
            httpStatus: 403,
            message: 'Key not valid',
        );

        $log = SeoIndexingLog::fromResult($result, queued: false);

        $this->assertDatabaseHas('seo_indexing_logs', [
            'url'         => 'https://example.com/page',
            'engine'      => 'indexnow',
            'action'      => 'URL_DELETED',
            'success'     => false,
            'http_status' => 403,
            'message'     => 'Key not valid',
            'queued'      => false,
        ]);
    }

    public function test_successful_scope(): void
    {
        SeoIndexingLog::fromResult(IndexingResult::success(engine: 'google', url: 'https://a.com', action: 'URL_UPDATED'));
        SeoIndexingLog::fromResult(IndexingResult::failure(engine: 'google', url: 'https://b.com', action: 'URL_UPDATED', httpStatus: 500, message: 'err'));

        $this->assertCount(1, SeoIndexingLog::successful()->get());
        $this->assertSame('https://a.com', SeoIndexingLog::successful()->first()->url);
    }

    public function test_failed_scope(): void
    {
        SeoIndexingLog::fromResult(IndexingResult::success(engine: 'google', url: 'https://a.com', action: 'URL_UPDATED'));
        SeoIndexingLog::fromResult(IndexingResult::failure(engine: 'google', url: 'https://b.com', action: 'URL_UPDATED', httpStatus: 500, message: 'err'));

        $this->assertCount(1, SeoIndexingLog::failed()->get());
        $this->assertSame('https://b.com', SeoIndexingLog::failed()->first()->url);
    }

    public function test_for_engine_scope(): void
    {
        SeoIndexingLog::fromResult(IndexingResult::success(engine: 'google', url: 'https://a.com', action: 'URL_UPDATED'));
        SeoIndexingLog::fromResult(IndexingResult::success(engine: 'indexnow:api.indexnow.org', url: 'https://a.com', action: 'URL_UPDATED'));

        $this->assertCount(1, SeoIndexingLog::forEngine('google')->get());
        $this->assertCount(1, SeoIndexingLog::forEngine('indexnow')->get());
    }

    public function test_for_url_scope(): void
    {
        SeoIndexingLog::fromResult(IndexingResult::success(engine: 'google', url: 'https://a.com', action: 'URL_UPDATED'));
        SeoIndexingLog::fromResult(IndexingResult::success(engine: 'google', url: 'https://b.com', action: 'URL_UPDATED'));

        $this->assertCount(1, SeoIndexingLog::forUrl('https://a.com')->get());
    }

    public function test_for_action_scope(): void
    {
        SeoIndexingLog::fromResult(IndexingResult::success(engine: 'google', url: 'https://a.com', action: 'URL_UPDATED'));
        SeoIndexingLog::fromResult(IndexingResult::success(engine: 'google', url: 'https://a.com', action: 'URL_DELETED'));

        $this->assertCount(1, SeoIndexingLog::forAction('URL_UPDATED')->get());
        $this->assertCount(1, SeoIndexingLog::forAction('URL_DELETED')->get());
    }

    public function test_recent_scope(): void
    {
        SeoIndexingLog::fromResult(IndexingResult::success(engine: 'google', url: 'https://a.com', action: 'URL_UPDATED'));

        $this->assertCount(1, SeoIndexingLog::recent(7)->get());

        // Age the record
        SeoIndexingLog::query()->update(['created_at' => now()->subDays(10)]);

        $this->assertCount(0, SeoIndexingLog::recent(7)->get());
    }

    public function test_prunable_returns_old_records(): void
    {
        $this->app['config']->set('seo-indexing.logging.retention_days', 30);

        $log = SeoIndexingLog::fromResult(IndexingResult::success(engine: 'google', url: 'https://a.com', action: 'URL_UPDATED'));

        // Fresh record should not be prunable
        $this->assertCount(0, $log->prunable()->get());

        // Age beyond retention
        SeoIndexingLog::query()->update(['created_at' => now()->subDays(31)]);

        $this->assertCount(1, $log->prunable()->get());
    }

    public function test_prunable_returns_nothing_when_retention_is_zero(): void
    {
        $this->app['config']->set('seo-indexing.logging.retention_days', 0);

        $log = SeoIndexingLog::fromResult(IndexingResult::success(engine: 'google', url: 'https://a.com', action: 'URL_UPDATED'));

        SeoIndexingLog::query()->update(['created_at' => now()->subDays(365)]);

        $this->assertCount(0, $log->prunable()->get());
    }

    public function test_casts_are_correct(): void
    {
        $log = SeoIndexingLog::fromResult(
            IndexingResult::success(engine: 'google', url: 'https://a.com', action: 'URL_UPDATED', payload: ['key' => 'val']),
            queued: true,
        );

        $log->refresh();

        $this->assertIsBool($log->success);
        $this->assertIsBool($log->queued);
        $this->assertIsArray($log->payload);
    }
}
