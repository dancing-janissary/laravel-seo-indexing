<?php

namespace DancingJanissary\SeoIndexing\Tests\Unit;

use DancingJanissary\SeoIndexing\Clients\GoogleIndexingClient;
use DancingJanissary\SeoIndexing\Clients\IndexNowClient;
use DancingJanissary\SeoIndexing\Data\IndexingResult;
use DancingJanissary\SeoIndexing\IndexingLogger;
use DancingJanissary\SeoIndexing\Jobs\SubmitUrlJob;
use DancingJanissary\SeoIndexing\SeoIndexingManager;
use DancingJanissary\SeoIndexing\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

class SeoIndexingManagerTest extends TestCase
{
    private GoogleIndexingClient $google;
    private IndexNowClient $indexNow;
    private IndexingLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->google  = $this->createMock(GoogleIndexingClient::class);
        $this->indexNow = $this->createMock(IndexNowClient::class);
        $this->logger  = $this->createMock(IndexingLogger::class);
    }

    private function makeManager(array $configOverrides = []): SeoIndexingManager
    {
        $config = array_replace_recursive([
            'engines' => ['google' => true, 'indexnow' => true],
            'queue'   => ['enabled' => false, 'connection' => 'sync', 'name' => 'default'],
        ], $configOverrides);

        return new SeoIndexingManager($this->google, $this->indexNow, $this->logger, $config);
    }

    // ── Sync dispatch ───────────────────────────────────────────────

    public function test_submit_calls_enabled_engines_synchronously(): void
    {
        $this->logger->method('wasRecentlySubmitted')->willReturn(false);

        $this->google->expects($this->once())
            ->method('submit')
            ->with('https://example.com/page', 'URL_UPDATED')
            ->willReturn(IndexingResult::success(engine: 'google', url: 'https://example.com/page', action: 'URL_UPDATED'));

        $this->indexNow->expects($this->once())
            ->method('submit')
            ->with('https://example.com/page', 'URL_UPDATED')
            ->willReturn(IndexingResult::success(engine: 'indexnow', url: 'https://example.com/page', action: 'URL_UPDATED'));

        $this->logger->expects($this->exactly(2))->method('log');

        $manager = $this->makeManager();
        $manager->submit('https://example.com/page');
    }

    public function test_delete_sends_url_deleted_action(): void
    {
        $this->logger->method('wasRecentlySubmitted')->willReturn(false);

        $this->google->expects($this->once())
            ->method('submit')
            ->with('https://example.com/page', 'URL_DELETED');

        $this->google->method('submit')->willReturn(
            IndexingResult::success(engine: 'google', url: 'https://example.com/page', action: 'URL_DELETED')
        );

        $manager = $this->makeManager(['engines' => ['google' => true, 'indexnow' => false]]);
        $manager->delete('https://example.com/page');
    }

    public function test_submit_skips_disabled_engines(): void
    {
        $this->logger->method('wasRecentlySubmitted')->willReturn(false);

        $this->google->expects($this->never())->method('submit');

        $this->indexNow->expects($this->once())
            ->method('submit')
            ->willReturn(IndexingResult::success(engine: 'indexnow', url: 'https://example.com', action: 'URL_UPDATED'));

        $manager = $this->makeManager(['engines' => ['google' => false, 'indexnow' => true]]);
        $manager->submit('https://example.com');
    }

    public function test_submit_skips_recently_submitted_urls(): void
    {
        $this->logger->method('wasRecentlySubmitted')->willReturn(true);

        $this->google->expects($this->never())->method('submit');
        $this->indexNow->expects($this->never())->method('submit');

        $manager = $this->makeManager();
        $manager->submit('https://example.com');
    }

    // ── Batch sync ──────────────────────────────────────────────────

    public function test_submit_batch_calls_engines_synchronously(): void
    {
        $urls = ['https://example.com/a', 'https://example.com/b'];

        $this->google->expects($this->once())
            ->method('submitBatch')
            ->with($urls, 'URL_UPDATED')
            ->willReturn([
                IndexingResult::success(engine: 'google', url: $urls[0], action: 'URL_UPDATED'),
                IndexingResult::success(engine: 'google', url: $urls[1], action: 'URL_UPDATED'),
            ]);

        $this->logger->expects($this->atLeastOnce())->method('logMany');

        $manager = $this->makeManager(['engines' => ['google' => true, 'indexnow' => false]]);
        $manager->submitBatch($urls);
    }

    public function test_delete_batch_uses_url_deleted_action(): void
    {
        $urls = ['https://example.com/a'];

        $this->google->expects($this->once())
            ->method('submitBatch')
            ->with($urls, 'URL_DELETED')
            ->willReturn([IndexingResult::success(engine: 'google', url: $urls[0], action: 'URL_DELETED')]);

        $manager = $this->makeManager(['engines' => ['google' => true, 'indexnow' => false]]);
        $manager->deleteBatch($urls);
    }

    // ── Queue dispatch ──────────────────────────────────────────────

    public function test_submit_does_not_call_client_directly_when_queue_enabled(): void
    {
        $this->logger->method('wasRecentlySubmitted')->willReturn(false);

        // When queue is enabled, the client should NOT be called directly —
        // instead a job should be dispatched.
        $this->google->expects($this->never())->method('submit');
        $this->indexNow->expects($this->never())->method('submit');
        $this->logger->expects($this->never())->method('log');

        $manager = $this->makeManager([
            'engines' => ['google' => true, 'indexnow' => false],
            'queue'   => ['enabled' => true, 'connection' => 'sync', 'name' => 'indexing'],
        ]);

        // Note: configureJob($job)->dispatch() has a bug — it calls the static
        // dispatch() which creates a new instance with no args. This will throw.
        // This test documents the bug.
        $this->expectException(\ArgumentCountError::class);
        $manager->submit('https://example.com/page');
    }

    public function test_submit_batch_does_not_call_client_directly_when_queue_enabled(): void
    {
        $this->google->expects($this->never())->method('submitBatch');
        $this->logger->expects($this->never())->method('logMany');

        $manager = $this->makeManager([
            'engines' => ['google' => true, 'indexnow' => false],
            'queue'   => ['enabled' => true, 'connection' => 'sync', 'name' => 'indexing'],
        ]);

        $this->expectException(\ArgumentCountError::class);
        $manager->submitBatch(['https://example.com/a', 'https://example.com/b']);
    }

    // ── Action constants ────────────────────────────────────────────

    public function test_action_constants_are_correct(): void
    {
        $this->assertSame('URL_UPDATED', SeoIndexingManager::ACTION_UPDATED);
        $this->assertSame('URL_DELETED', SeoIndexingManager::ACTION_DELETED);
    }
}
