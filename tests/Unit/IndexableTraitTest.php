<?php

namespace DancingJanissary\SeoIndexing\Tests\Unit;

use DancingJanissary\SeoIndexing\Clients\GoogleIndexingClient;
use DancingJanissary\SeoIndexing\Clients\IndexNowClient;
use DancingJanissary\SeoIndexing\SeoIndexingManager;
use DancingJanissary\SeoIndexing\Tests\TestCase;
use DancingJanissary\SeoIndexing\Traits\Indexable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IndexableTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        // Reset indexing toggle between tests
        TestPage::enableIndexing();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_pages');
        TestPage::enableIndexing();
        parent::tearDown();
    }

    private function mockManagerExpectsSubmit(int $times = 1): void
    {
        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->exactly($times))->method('submit');
        $this->app->instance(SeoIndexingManager::class, $manager);
    }

    private function mockManagerExpectsDelete(int $times = 1): void
    {
        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->exactly($times))->method('delete');
        $this->app->instance(SeoIndexingManager::class, $manager);
    }

    private function mockManagerExpectsNothing(): void
    {
        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->never())->method('submit');
        $manager->expects($this->never())->method('delete');
        $this->app->instance(SeoIndexingManager::class, $manager);
    }

    // ── URL generation ──────────────────────────────────────────────

    public function test_get_indexable_url_uses_slug_when_available(): void
    {
        $page = new TestPage(['title' => 'Test', 'slug' => 'my-page']);
        $page->id = 1;

        $this->assertStringEndsWith('/my-page', $page->getIndexableUrl());
    }

    public function test_get_indexable_url_falls_back_to_key(): void
    {
        $page = new TestPage(['title' => 'Test']);
        $page->id = 42;

        $this->assertStringEndsWith('/42', $page->getIndexableUrl());
    }

    // ── Model events ────────────────────────────────────────────────

    public function test_saved_event_triggers_submit(): void
    {
        $this->mockManagerExpectsSubmit();

        TestPage::create(['title' => 'New Page', 'slug' => 'new-page']);
    }

    public function test_deleted_event_triggers_delete(): void
    {
        $this->mockManagerExpectsNothing();
        TestPage::disableIndexing();
        $page = TestPage::create(['title' => 'Test', 'slug' => 'test']);
        TestPage::enableIndexing();

        $this->mockManagerExpectsDelete();
        $page->delete();
    }

    // ── Indexing toggle ─────────────────────────────────────────────

    public function test_disable_indexing_prevents_events(): void
    {
        $this->mockManagerExpectsNothing();

        TestPage::disableIndexing();
        TestPage::create(['title' => 'No Index']);
    }

    public function test_enable_indexing_restores_events(): void
    {
        TestPage::disableIndexing();
        TestPage::enableIndexing();

        $this->mockManagerExpectsSubmit();
        TestPage::create(['title' => 'Yes Index']);
    }

    public function test_is_indexing_enabled_returns_correct_state(): void
    {
        $this->assertTrue(TestPage::isIndexingEnabled());

        TestPage::disableIndexing();
        $this->assertFalse(TestPage::isIndexingEnabled());

        TestPage::enableIndexing();
        $this->assertTrue(TestPage::isIndexingEnabled());
    }

    public function test_without_indexing_temporarily_disables_and_restores(): void
    {
        $page = new TestPage(['title' => 'Test']);
        $page->id = 1;

        $this->mockManagerExpectsNothing();

        $result = $page->withoutIndexing(function () {
            $this->assertFalse(TestPage::isIndexingEnabled());
            return 'callback-result';
        });

        $this->assertSame('callback-result', $result);
        $this->assertTrue(TestPage::isIndexingEnabled());
    }

    public function test_without_indexing_restores_on_exception(): void
    {
        $page = new TestPage(['title' => 'Test']);
        $page->id = 1;

        try {
            $page->withoutIndexing(function () {
                throw new \RuntimeException('oops');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue(TestPage::isIndexingEnabled());
    }

    // ── Manual index() ──────────────────────────────────────────────

    public function test_index_method_submits_as_updated_by_default(): void
    {
        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->once())->method('submit');
        $this->app->instance(SeoIndexingManager::class, $manager);

        // Disable auto-indexing to avoid event-driven call
        TestPage::disableIndexing();
        $page = TestPage::create(['title' => 'Manual', 'slug' => 'manual']);
        TestPage::enableIndexing();

        $page->index();
    }

    public function test_index_method_with_deleted_action(): void
    {
        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->once())->method('delete');
        $this->app->instance(SeoIndexingManager::class, $manager);

        TestPage::disableIndexing();
        $page = TestPage::create(['title' => 'Manual', 'slug' => 'manual']);
        TestPage::enableIndexing();

        $page->index(SeoIndexingManager::ACTION_DELETED);
    }

    public function test_index_method_throws_for_invalid_action(): void
    {
        TestPage::disableIndexing();
        $page = TestPage::create(['title' => 'Test']);
        TestPage::enableIndexing();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid indexing action');

        $page->index('INVALID_ACTION');
    }

    // ── shouldIndex ─────────────────────────────────────────────────

    public function test_should_index_returns_true_by_default(): void
    {
        $page = new TestPage(['title' => 'Test']);
        $this->assertTrue($page->shouldIndex());
    }

    public function test_should_index_returns_false_when_disabled(): void
    {
        TestPage::disableIndexing();
        $page = new TestPage(['title' => 'Test']);
        $this->assertFalse($page->shouldIndex());
    }
}

/**
 * Test model using the Indexable trait.
 */
class TestPage extends Model
{
    use Indexable;

    protected $table = 'test_pages';
    protected $guarded = [];
}
