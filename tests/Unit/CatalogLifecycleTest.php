<?php

namespace Tests\Unit;

use App\Models\ScrapedProduct;
use App\Models\ScrapeUrl;
use App\Services\Catalog\CatalogQdrantSyncService;
use App\Services\Catalog\QdrantCatalogClient;
use App\Services\Scraper\VegaScraper;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CatalogLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
    }

    private function createTables(): void
    {
        if (! Schema::hasTable('scrape_urls')) {
            Schema::create('scrape_urls', function ($table) {
                $table->id();
                $table->string('source_marketplace', 32)->index();
                $table->string('url', 1024);
                $table->string('url_hash', 64);
                $table->string('external_id', 128)->nullable();
                $table->string('discovery_source', 16)->default('category');
                $table->enum('status', ['pending', 'done', 'failed'])->default('pending');
                $table->smallInteger('fail_count')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('scraped_at')->nullable();
                $table->timestamp('last_seen_in_listing_at')->nullable();
                $table->timestamp('last_verified_at')->nullable();
                $table->timestamps();
                $table->unique(['source_marketplace', 'url_hash'], 'scrape_urls_marketplace_url_unique');
            });
        }

        if (! Schema::hasTable('scraped_products')) {
            Schema::create('scraped_products', function ($table) {
                $table->id();
                $table->string('source_marketplace', 32)->index();
                $table->string('external_url', 1024);
                $table->string('external_url_hash', 64);
                $table->string('name', 512);
                $table->string('name_en', 512)->nullable();
                $table->unsignedInteger('price')->default(0);
                $table->string('currency', 8)->default('AMD');
                $table->boolean('in_stock')->default(true);
                $table->timestamp('scraped_at')->nullable();
                $table->timestamp('unavailable_at')->nullable();
                $table->timestamp('embedded_at')->nullable();
                $table->timestamps();
                $table->unique(['source_marketplace', 'external_url_hash'], 'scraped_products_marketplace_url_unique');
            });
        }
    }

    // ── reconcileListingRemovals ─────────────────────────────────────

    public function test_reconcile_deactivates_products_missing_from_listing(): void
    {
        $sweepStartedAt = now();

        // Stale product (not seen in this sweep)
        $staleHash = hash('sha256', 'https://vega.am/en/sofa.html');
        ScrapeUrl::create([
            'source_marketplace' => 'vega',
            'url' => 'https://vega.am/en/sofa.html',
            'url_hash' => $staleHash,
            'status' => 'done',
            'discovery_source' => 'category',
            'last_seen_in_listing_at' => now()->subDays(10),
        ]);
        ScrapedProduct::create([
            'source_marketplace' => 'vega',
            'external_url' => 'https://vega.am/en/sofa.html',
            'external_url_hash' => $staleHash,
            'name' => 'Test Sofa',
            'price' => 100000,
            'in_stock' => true,
        ]);

        // Two fresh products so stale count (1) is < 50% of active (3)
        for ($i = 0; $i < 2; $i++) {
            $url = "https://vega.am/en/active-{$i}.html";
            $hash = hash('sha256', $url);
            ScrapeUrl::create([
                'source_marketplace' => 'vega',
                'url' => $url,
                'url_hash' => $hash,
                'status' => 'done',
                'discovery_source' => 'category',
                'last_seen_in_listing_at' => now(),
            ]);
            ScrapedProduct::create([
                'source_marketplace' => 'vega',
                'external_url' => $url,
                'external_url_hash' => $hash,
                'name' => "Active Product {$i}",
                'price' => 80000,
                'in_stock' => true,
            ]);
        }

        $scraper = new VegaScraper;
        $deactivated = $scraper->reconcileListingRemovals($sweepStartedAt);

        $this->assertSame(1, $deactivated);

        $product = ScrapedProduct::where('external_url_hash', $staleHash)->first();
        $this->assertFalse($product->in_stock);
        $this->assertNotNull($product->unavailable_at);
    }

    public function test_reconcile_skips_search_discovered_urls(): void
    {
        $sweepStartedAt = now();

        $urlHash = hash('sha256', 'https://vega.am/en/chair.html');
        ScrapeUrl::create([
            'source_marketplace' => 'vega',
            'url' => 'https://vega.am/en/chair.html',
            'url_hash' => $urlHash,
            'status' => 'done',
            'discovery_source' => 'search',
            'last_seen_in_listing_at' => now()->subDays(30),
        ]);

        ScrapedProduct::create([
            'source_marketplace' => 'vega',
            'external_url' => 'https://vega.am/en/chair.html',
            'external_url_hash' => $urlHash,
            'name' => 'Search Chair',
            'price' => 50000,
            'in_stock' => true,
        ]);

        $scraper = new VegaScraper;
        $deactivated = $scraper->reconcileListingRemovals($sweepStartedAt);

        $this->assertSame(0, $deactivated);

        $product = ScrapedProduct::where('external_url_hash', $urlHash)->first();
        $this->assertTrue($product->in_stock);
    }

    public function test_reconcile_aborts_when_deactivation_exceeds_50_percent(): void
    {
        $sweepStartedAt = now();

        for ($i = 0; $i < 2; $i++) {
            $url = "https://vega.am/en/product-{$i}.html";
            $urlHash = hash('sha256', $url);

            ScrapeUrl::create([
                'source_marketplace' => 'vega',
                'url' => $url,
                'url_hash' => $urlHash,
                'status' => 'done',
                'discovery_source' => 'category',
                'last_seen_in_listing_at' => now()->subDays(10),
            ]);

            ScrapedProduct::create([
                'source_marketplace' => 'vega',
                'external_url' => $url,
                'external_url_hash' => $urlHash,
                'name' => "Product {$i}",
                'price' => 50000,
                'in_stock' => true,
            ]);
        }

        $scraper = new VegaScraper;
        $deactivated = $scraper->reconcileListingRemovals($sweepStartedAt);

        $this->assertSame(0, $deactivated);
        $this->assertSame(2, ScrapedProduct::where('in_stock', true)->count());
    }

    public function test_reconcile_preserves_recently_seen_urls(): void
    {
        $sweepStartedAt = now()->subMinutes(5);

        $urlHash = hash('sha256', 'https://vega.am/en/table.html');
        ScrapeUrl::create([
            'source_marketplace' => 'vega',
            'url' => 'https://vega.am/en/table.html',
            'url_hash' => $urlHash,
            'status' => 'done',
            'discovery_source' => 'category',
            'last_seen_in_listing_at' => now(),
        ]);

        ScrapedProduct::create([
            'source_marketplace' => 'vega',
            'external_url' => 'https://vega.am/en/table.html',
            'external_url_hash' => $urlHash,
            'name' => 'Active Table',
            'price' => 80000,
            'in_stock' => true,
        ]);

        $scraper = new VegaScraper;
        $deactivated = $scraper->reconcileListingRemovals($sweepStartedAt);

        $this->assertSame(0, $deactivated);

        $product = ScrapedProduct::where('external_url_hash', $urlHash)->first();
        $this->assertTrue($product->in_stock);
    }

    // ── Qdrant sync service ─────────────────────────────────────────

    public function test_sync_deactivated_calls_set_payload_with_false(): void
    {
        $urlHash = hash('sha256', 'https://vega.am/en/old-sofa.html');
        $product = ScrapedProduct::create([
            'source_marketplace' => 'vega',
            'external_url' => 'https://vega.am/en/old-sofa.html',
            'external_url_hash' => $urlHash,
            'name' => 'Old Sofa',
            'price' => 100000,
            'in_stock' => false,
            'unavailable_at' => now(),
            'embedded_at' => now()->subDay(),
        ]);

        $mockClient = Mockery::mock(QdrantCatalogClient::class);
        $mockClient->shouldReceive('setPayload')
            ->once()
            ->with([$product->id], ['in_stock' => false]);

        $service = new CatalogQdrantSyncService($mockClient);
        $synced = $service->syncDeactivated('vega');

        $this->assertSame(1, $synced);
    }

    public function test_sync_reactivated_calls_set_payload_with_true(): void
    {
        $urlHash = hash('sha256', 'https://vega.am/en/new-sofa.html');
        $product = ScrapedProduct::create([
            'source_marketplace' => 'vega',
            'external_url' => 'https://vega.am/en/new-sofa.html',
            'external_url_hash' => $urlHash,
            'name' => 'New Sofa',
            'price' => 100000,
            'in_stock' => true,
            'unavailable_at' => null,
            'embedded_at' => now()->subDay(),
        ]);

        $mockClient = Mockery::mock(QdrantCatalogClient::class);
        $mockClient->shouldReceive('setPayload')
            ->once()
            ->with([$product->id], ['in_stock' => true]);

        $service = new CatalogQdrantSyncService($mockClient);
        $synced = $service->syncReactivated('vega');

        $this->assertSame(1, $synced);
    }

    public function test_delete_unavailable_removes_points_and_clears_embedded_at(): void
    {
        $urlHash = hash('sha256', 'https://vega.am/en/gone-sofa.html');
        $product = ScrapedProduct::create([
            'source_marketplace' => 'vega',
            'external_url' => 'https://vega.am/en/gone-sofa.html',
            'external_url_hash' => $urlHash,
            'name' => 'Gone Sofa',
            'price' => 100000,
            'in_stock' => false,
            'embedded_at' => now()->subDay(),
        ]);

        $mockClient = Mockery::mock(QdrantCatalogClient::class);
        $mockClient->shouldReceive('deleteProducts')
            ->once()
            ->with([$product->id]);

        $service = new CatalogQdrantSyncService($mockClient);
        $deleted = $service->deleteUnavailable('vega');

        $this->assertSame(1, $deleted);
        $this->assertNull($product->fresh()->embedded_at);
    }

    public function test_prune_orphans_deletes_points_not_in_active_products(): void
    {
        $urlHash = hash('sha256', 'https://vega.am/en/active.html');
        $activeProduct = ScrapedProduct::create([
            'source_marketplace' => 'vega',
            'external_url' => 'https://vega.am/en/active.html',
            'external_url_hash' => $urlHash,
            'name' => 'Active Product',
            'price' => 100000,
            'in_stock' => true,
        ]);

        $orphanPointId = 99999;

        $mockClient = Mockery::mock(QdrantCatalogClient::class);
        $mockClient->shouldReceive('scrollAllIds')
            ->once()
            ->andReturn([$activeProduct->id, $orphanPointId]);
        $mockClient->shouldReceive('deleteProducts')
            ->once()
            ->with([$orphanPointId]);

        $service = new CatalogQdrantSyncService($mockClient);
        $pruned = $service->pruneOrphans();

        $this->assertSame(1, $pruned);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
