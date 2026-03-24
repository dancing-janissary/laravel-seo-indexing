<?php

namespace DancingJanissary\SeoIndexing\Tests\Unit;

use DancingJanissary\SeoIndexing\SeoIndexingManager;
use DancingJanissary\SeoIndexing\Tests\TestCase;
use DancingJanissary\SeoIndexing\Traits\Indexable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MultiLanguageIndexableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('ml_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('ml_soft_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        MultiLangPage::enableIndexing();
        MultiLangSoftPage::enableIndexing();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('ml_pages');
        Schema::dropIfExists('ml_soft_pages');
        MultiLangPage::enableIndexing();
        MultiLangSoftPage::enableIndexing();
        parent::tearDown();
    }

    // ── getIndexableUrls / resolveIndexableUrls ─────────────────────

    public function test_get_indexable_urls_returns_null_by_default(): void
    {
        $page = new SingleLangPage(['title' => 'Test']);
        $this->assertNull($page->getIndexableUrls());
    }

    public function test_resolve_falls_back_to_single_url_when_get_indexable_urls_returns_null(): void
    {
        $page = new SingleLangPage(['title' => 'Test', 'slug' => 'hello']);
        $page->id = 1;

        $urls = $this->callResolve($page);

        $this->assertCount(1, $urls);
        $this->assertStringEndsWith('/hello', $urls[0]);
    }

    public function test_resolve_falls_back_to_single_url_when_get_indexable_urls_returns_empty_array(): void
    {
        $page = new EmptyUrlsPage(['title' => 'Test', 'slug' => 'fallback']);
        $page->id = 1;

        $urls = $this->callResolve($page);

        $this->assertCount(1, $urls);
        $this->assertStringEndsWith('/fallback', $urls[0]);
    }

    public function test_resolve_returns_all_locale_urls(): void
    {
        $page = new MultiLangPage(['title' => 'Test', 'slug' => 'my-page']);
        $page->id = 1;

        $urls = $this->callResolve($page);

        $this->assertCount(3, $urls);
        $this->assertStringContainsString('/en/my-page', $urls[0]);
        $this->assertStringContainsString('/fr/my-page', $urls[1]);
        $this->assertStringContainsString('/de/my-page', $urls[2]);
    }

    public function test_resolve_strips_associative_keys(): void
    {
        $page = new MultiLangPage(['title' => 'Test', 'slug' => 'my-page']);
        $page->id = 1;

        $urls = $this->callResolve($page);

        // Keys should be 0, 1, 2 — not 'en', 'fr', 'de'
        $this->assertSame([0, 1, 2], array_keys($urls));
    }

    // ── Model events with multi-language ────────────────────────────

    public function test_saved_event_calls_submit_batch_for_multi_lang_model(): void
    {
        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->once())
            ->method('submitBatch')
            ->with(
                $this->callback(function (array $urls) {
                    return count($urls) === 3
                        && str_contains($urls[0], '/en/')
                        && str_contains($urls[1], '/fr/')
                        && str_contains($urls[2], '/de/');
                }),
                $this->isInstanceOf(Model::class),
            );
        $manager->expects($this->never())->method('submit');
        $this->app->instance(SeoIndexingManager::class, $manager);

        MultiLangPage::create(['title' => 'Multi', 'slug' => 'multi']);
    }

    public function test_deleted_event_calls_delete_batch_for_multi_lang_model(): void
    {
        MultiLangPage::disableIndexing();
        $page = MultiLangPage::create(['title' => 'Multi', 'slug' => 'multi']);
        MultiLangPage::enableIndexing();

        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->once())
            ->method('deleteBatch')
            ->with(
                $this->callback(fn (array $urls) => count($urls) === 3),
                $this->isInstanceOf(Model::class),
            );
        $manager->expects($this->never())->method('delete');
        $this->app->instance(SeoIndexingManager::class, $manager);

        $page->delete();
    }

    public function test_restored_event_calls_submit_batch_for_multi_lang_soft_delete_model(): void
    {
        MultiLangSoftPage::disableIndexing();
        $page = MultiLangSoftPage::create(['title' => 'Soft', 'slug' => 'soft']);
        $page->delete();
        MultiLangSoftPage::enableIndexing();

        $manager = $this->createMock(SeoIndexingManager::class);
        // Restore fires both 'restored' and 'saved' events, so submitBatch
        // is called twice — once for each event.
        $manager->expects($this->exactly(2))
            ->method('submitBatch')
            ->with(
                $this->callback(fn (array $urls) => count($urls) === 3),
                $this->isInstanceOf(Model::class),
            );
        $this->app->instance(SeoIndexingManager::class, $manager);

        $page->restore();
    }

    // ── Single-lang model still uses submit/delete (not batch) ──────

    public function test_saved_event_calls_submit_for_single_lang_model(): void
    {
        Schema::create('sl_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->once())->method('submit');
        $manager->expects($this->never())->method('submitBatch');
        $this->app->instance(SeoIndexingManager::class, $manager);

        SingleLangPage::create(['title' => 'Single', 'slug' => 'single']);

        Schema::dropIfExists('sl_pages');
    }

    public function test_deleted_event_calls_delete_for_single_lang_model(): void
    {
        Schema::create('sl_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        SingleLangPage::disableIndexing();
        $page = SingleLangPage::create(['title' => 'Single', 'slug' => 'single']);
        SingleLangPage::enableIndexing();

        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->once())->method('delete');
        $manager->expects($this->never())->method('deleteBatch');
        $this->app->instance(SeoIndexingManager::class, $manager);

        $page->delete();

        Schema::dropIfExists('sl_pages');
    }

    // ── Manual index() with multi-language ──────────────────────────

    public function test_index_method_calls_submit_batch_for_multi_lang(): void
    {
        MultiLangPage::disableIndexing();
        $page = MultiLangPage::create(['title' => 'Manual', 'slug' => 'manual']);
        MultiLangPage::enableIndexing();

        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->once())
            ->method('submitBatch')
            ->with(
                $this->callback(fn (array $urls) => count($urls) === 3),
                $this->isInstanceOf(Model::class),
            );
        $this->app->instance(SeoIndexingManager::class, $manager);

        $page->index();
    }

    public function test_index_method_with_deleted_action_calls_delete_batch_for_multi_lang(): void
    {
        MultiLangPage::disableIndexing();
        $page = MultiLangPage::create(['title' => 'Manual', 'slug' => 'manual']);
        MultiLangPage::enableIndexing();

        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->once())->method('deleteBatch');
        $this->app->instance(SeoIndexingManager::class, $manager);

        $page->index(SeoIndexingManager::ACTION_DELETED);
    }

    public function test_index_method_throws_for_invalid_action_on_multi_lang(): void
    {
        MultiLangPage::disableIndexing();
        $page = MultiLangPage::create(['title' => 'Test', 'slug' => 'test']);
        MultiLangPage::enableIndexing();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid indexing action');

        $page->index('INVALID_ACTION');
    }

    // ── shouldIndex controls multi-lang too ─────────────────────────

    public function test_should_index_false_prevents_multi_lang_batch(): void
    {
        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->never())->method('submit');
        $manager->expects($this->never())->method('submitBatch');
        $manager->expects($this->never())->method('delete');
        $manager->expects($this->never())->method('deleteBatch');
        $this->app->instance(SeoIndexingManager::class, $manager);

        MultiLangPage::disableIndexing();
        MultiLangPage::create(['title' => 'Hidden', 'slug' => 'hidden']);
    }

    public function test_without_indexing_prevents_multi_lang_batch(): void
    {
        $manager = $this->createMock(SeoIndexingManager::class);
        $manager->expects($this->never())->method('submitBatch');
        $this->app->instance(SeoIndexingManager::class, $manager);

        MultiLangPage::disableIndexing();
        $page = MultiLangPage::create(['title' => 'Temp', 'slug' => 'temp']);
        MultiLangPage::enableIndexing();

        $page->withoutIndexing(function () use ($page) {
            $page->update(['title' => 'Updated']);
        });
    }

    // ── Helper ──────────────────────────────────────────────────────

    private function callResolve(Model $model): array
    {
        $reflection = new \ReflectionMethod($model, 'resolveIndexableUrls');
        $reflection->setAccessible(true);

        return $reflection->invoke($model);
    }
}

/**
 * Multi-language model — returns 3 locale URLs.
 */
class MultiLangPage extends Model
{
    use Indexable;

    protected $table = 'ml_pages';
    protected $guarded = [];

    public function getIndexableUrls(): ?array
    {
        return [
            'en' => url('/en/' . ($this->slug ?? $this->getKey())),
            'fr' => url('/fr/' . ($this->slug ?? $this->getKey())),
            'de' => url('/de/' . ($this->slug ?? $this->getKey())),
        ];
    }
}

/**
 * Multi-language model with SoftDeletes.
 */
class MultiLangSoftPage extends Model
{
    use Indexable, SoftDeletes;

    protected $table = 'ml_soft_pages';
    protected $guarded = [];

    public function getIndexableUrls(): ?array
    {
        return [
            'en' => url('/en/' . ($this->slug ?? $this->getKey())),
            'fr' => url('/fr/' . ($this->slug ?? $this->getKey())),
            'de' => url('/de/' . ($this->slug ?? $this->getKey())),
        ];
    }
}

/**
 * Single-language model — does NOT override getIndexableUrls().
 */
class SingleLangPage extends Model
{
    use Indexable;

    protected $table = 'sl_pages';
    protected $guarded = [];
}

/**
 * Model that returns empty array from getIndexableUrls().
 */
class EmptyUrlsPage extends Model
{
    use Indexable;

    protected $table = 'ml_pages';
    protected $guarded = [];

    public function getIndexableUrls(): ?array
    {
        return [];
    }
}
