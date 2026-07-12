<?php

namespace Tests;

use App\Models\ScrapedProduct;
use App\Services\Catalog\CatalogRerankService;
use App\Services\Catalog\CatalogSlotResolver;
use App\Services\Catalog\DimensionScorer;
use App\Services\Catalog\EmbeddingTextBuilder;
use App\Services\Catalog\OpenAiEmbeddingClient;
use App\Services\Catalog\ProductSubtypeNormalizer;
use App\Services\Catalog\ProductTaxonomyClassifier;
use App\Services\Catalog\QdrantCatalogClient;
use App\Services\Catalog\RoomSubtypePolicy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class CatalogServicesTest extends TestCase
{
    use CreatesApplication;

    private function ensureScrapedProductsTable(): void
    {
        if (Schema::hasTable('scraped_products')) {
            return;
        }

        Schema::create('scraped_products', function ($table) {
            $table->id();
            $table->string('source_marketplace', 32)->index();
            $table->string('external_url', 1024)->nullable();
            $table->string('external_url_hash', 64);
            $table->string('name', 512);
            $table->unsignedInteger('price')->default(0);
            $table->string('product_family', 48)->nullable();
            $table->string('product_subtype', 48)->nullable();
            $table->timestamps();
            $table->unique(['source_marketplace', 'external_url_hash'], 'scraped_products_marketplace_url_unique');
        });
    }

    public function test_dimension_scorer_rejects_oversized_sofa(): void
    {
        $scorer = new DimensionScorer;
        $product = new ScrapedProduct([
            'has_dimensions' => true,
            'width_cm' => 400,
            'depth_cm' => 120,
            'height_cm' => 90,
            'product_subtype' => 'sofa',
        ]);

        $result = $scorer->score('sofa', ['width_m' => 3.5, 'depth_m' => 4.0, 'height_m' => 2.7], $product);

        $this->assertTrue($result['hard_reject']);
    }

    public function test_dimension_scorer_neutral_when_room_missing(): void
    {
        $scorer = new DimensionScorer;
        $product = new ScrapedProduct([
            'has_dimensions' => true,
            'width_cm' => 200,
            'depth_cm' => 90,
            'height_cm' => 85,
            'product_subtype' => 'sofa',
        ]);

        $result = $scorer->score('sofa', [], $product);

        $this->assertFalse($result['hard_reject']);
        $this->assertSame('room_dims_missing', $result['reason']);
    }

    public function test_embedding_text_builder_is_deterministic(): void
    {
        $builder = new EmbeddingTextBuilder;
        $product = new ScrapedProduct([
            'name' => 'Test Sofa',
            'name_en' => 'Test Sofa EN',
            'category_en' => 'Sofa',
            'product_family' => 'furniture',
            'product_subtype' => 'sofa',
            'material_tags' => ['linen'],
            'color_tags' => ['beige'],
            'has_dimensions' => true,
            'width_cm' => 220,
            'depth_cm' => 95,
            'height_cm' => 85,
        ]);

        $a = $builder->build($product);
        $b = $builder->build($product);

        $this->assertSame($a, $b);
        $this->assertStringContainsString('Seating for living room', $a);
    }

    public function test_room_subtype_policy_denies_bedroom_items_in_living_room(): void
    {
        $this->assertTrue(RoomSubtypePolicy::isSubtypeDeniedForRoom('living room', 'duvet'));
        $this->assertTrue(RoomSubtypePolicy::isSubtypeDeniedForRoom('living room', 'bedroom_set'));
        $this->assertFalse(RoomSubtypePolicy::isSubtypeDeniedForRoom('living room', 'sofa'));
    }

    public function test_room_subtype_policy_denies_appliances_in_living_room(): void
    {
        $this->assertTrue(RoomSubtypePolicy::isSubtypeDeniedForRoom('living room', 'washing_machine'));
        $this->assertTrue(RoomSubtypePolicy::isSubtypeDeniedForRoom('living_room', 'refrigerator'));
        $this->assertFalse(RoomSubtypePolicy::isSubtypeDeniedForRoom('laundry', 'washing_machine'));
        $this->assertFalse(RoomSubtypePolicy::isSubtypeDeniedForRoom('kitchen', 'refrigerator'));
        $this->assertTrue(RoomSubtypePolicy::isSubtypeDeniedForRoom('bedroom', 'refrigerator'));
        $this->assertTrue(RoomSubtypePolicy::isSubtypeDeniedForRoom('kitchen', 'washing_machine'));
        $this->assertFalse(RoomSubtypePolicy::isSubtypeDeniedForRoom('bathroom', 'washing_machine'));
    }

    public function test_room_subtype_policy_denies_home_appliances_family_outside_allowed_rooms(): void
    {
        $this->assertTrue(RoomSubtypePolicy::isFamilyDeniedForRoom('living room', 'home_appliances'));
        $this->assertTrue(RoomSubtypePolicy::isFamilyDeniedForRoom('bedroom', 'home_appliances'));
        $this->assertFalse(RoomSubtypePolicy::isFamilyDeniedForRoom('kitchen', 'home_appliances'));
        $this->assertFalse(RoomSubtypePolicy::isFamilyDeniedForRoom('laundry', 'home_appliances'));
        $this->assertFalse(RoomSubtypePolicy::isFamilyDeniedForRoom('bathroom', 'home_appliances'));
        $this->assertFalse(RoomSubtypePolicy::isFamilyDeniedForRoom('living room', 'furniture'));
    }

    public function test_room_score_normalizes_underscore_room_tags(): void
    {
        $product = new ScrapedProduct([
            'ai_tags' => ['rooms' => ['living_room']],
        ]);

        $reranker = new CatalogRerankService(new DimensionScorer);
        $method = new \ReflectionMethod(CatalogRerankService::class, 'roomScore');
        $method->setAccessible(true);

        $score = $method->invoke($reranker, $product, 'living_room');

        $this->assertSame(1.0, $score);
    }

    public function test_product_subtype_normalizer_maps_armchair_to_chair(): void
    {
        $this->assertSame('chair', ProductSubtypeNormalizer::normalize('furniture', 'armchair'));
        $this->assertSame('coffee_table', ProductSubtypeNormalizer::normalize('furniture', 'coffee table'));
        $this->assertSame('rug', ProductSubtypeNormalizer::normalize('flooring', 'area rug'));
    }

    public function test_product_subtype_normalizer_subtypes_match_armchair_and_chair(): void
    {
        $this->assertTrue(ProductSubtypeNormalizer::subtypesMatch('furniture', 'armchair', 'chair'));
    }

    public function test_rerank_rejects_subtype_mismatch(): void
    {
        $this->ensureScrapedProductsTable();

        $sofa = ScrapedProduct::create([
            'source_marketplace' => 'vega',
            'external_url' => 'https://vega.am/en/sofa-test.html',
            'name' => 'Real Sofa',
            'product_family' => 'furniture',
            'product_subtype' => 'sofa',
            'price' => 100000,
        ]);
        $vase = ScrapedProduct::create([
            'source_marketplace' => 'vega',
            'external_url' => 'https://vega.am/en/vase-test.html',
            'name' => 'Fruit vase',
            'product_family' => 'furniture',
            'product_subtype' => 'vase',
            'price' => 5000,
        ]);

        $reranker = new CatalogRerankService(new DimensionScorer);
        $result = $reranker->rerank(
            [
                ['product_id' => $vase->id, 'score' => 0.9, 'payload' => []],
                ['product_id' => $sofa->id, 'score' => 0.5, 'payload' => []],
            ],
            'furniture',
            'sofa',
            [],
            [],
        );

        $this->assertCount(1, $result['ranked']);
        $this->assertSame($sofa->id, $result['ranked'][0]['product_id']);
    }

    public function test_rerank_allows_product_with_missing_subtype(): void
    {
        $this->ensureScrapedProductsTable();

        $sofa = ScrapedProduct::create([
            'source_marketplace' => 'vega',
            'external_url' => 'https://vega.am/en/sofa-untyped.html',
            'name' => 'Untyped Sofa',
            'product_family' => 'furniture',
            'product_subtype' => null,
            'price' => 200000,
        ]);

        $reranker = new CatalogRerankService(new DimensionScorer);
        $result = $reranker->rerank(
            [['product_id' => $sofa->id, 'score' => 0.7, 'payload' => []]],
            'furniture',
            'sofa',
            [],
            [],
        );

        $this->assertCount(1, $result['ranked']);
        $this->assertSame($sofa->id, $result['ranked'][0]['product_id']);
    }

    public function test_dimension_scorer_does_not_reject_laminate_plank_dims(): void
    {
        $scorer = new DimensionScorer;
        $product = new ScrapedProduct([
            'has_dimensions' => true,
            'width_cm' => 138,
            'depth_cm' => 19,
            'height_cm' => 1,
            'product_subtype' => 'laminate',
        ]);

        $result = $scorer->score('laminate', ['width_m' => 4.0, 'depth_m' => 5.0, 'height_m' => 2.7], $product);

        $this->assertFalse($result['hard_reject']);
        $this->assertSame('flooring_surface', $result['reason']);
    }

    public function test_flooring_pick_selects_within_epsilon_band(): void
    {
        $resolver = new CatalogSlotResolver(
            $this->createMock(OpenAiEmbeddingClient::class),
            $this->createMock(QdrantCatalogClient::class),
            new CatalogRerankService(new DimensionScorer),
        );
        $method = new \ReflectionMethod($resolver, 'pickTop');
        $method->setAccessible(true);

        $ranked = [
            ['product_id' => 1, 'final_score' => 0.48],
            ['product_id' => 2, 'final_score' => 0.47],
            ['product_id' => 3, 'final_score' => 0.40],
        ];

        $pickedIds = [];
        for ($i = 0; $i < 30; $i++) {
            $result = $method->invoke($resolver, $ranked, 1, [], 'flooring');
            $pickedIds[] = $result[0]['product_id'];
        }

        $unique = array_unique($pickedIds);
        // Should pick from the top two (within epsilon 0.03) but never #3
        $this->assertNotContains(3, $pickedIds);
        $this->assertContains(1, $unique);
        // With 30 iterations, extremely likely to pick #2 at least once
        $this->assertContains(2, $unique);
    }

    public function test_rerank_relaxed_keeps_other_flooring_subtype_for_laminate_slot(): void
    {
        $this->ensureScrapedProductsTable();

        $tile = ScrapedProduct::create([
            'source_marketplace' => 'vega',
            'external_url' => 'https://vega.am/en/floor-tile-test.html',
            'name' => 'Porcelain floor tile',
            'product_family' => 'flooring',
            'product_subtype' => 'tile',
            'price' => 12000,
        ]);

        $reranker = new CatalogRerankService(new DimensionScorer);

        // Strict rejection (default): a tile is rejected for a laminate slot.
        $strict = $reranker->rerank(
            [['product_id' => $tile->id, 'score' => 0.9, 'payload' => []]],
            'flooring',
            'laminate',
            [],
            [],
            [],
            true,
            '',
            true,
        );
        $this->assertCount(0, $strict['ranked']);

        // Relaxed fallback (B1, flooring only): the tile survives for a laminate slot.
        $relaxed = $reranker->rerank(
            [['product_id' => $tile->id, 'score' => 0.9, 'payload' => []]],
            'flooring',
            'laminate',
            [],
            [],
            [],
            false,
            '',
            false,
        );
        $this->assertCount(1, $relaxed['ranked']);
        $this->assertSame($tile->id, $relaxed['ranked'][0]['product_id']);
    }

    public function test_taxonomy_classifier_tags_vase_and_excludes_cookware(): void
    {
        $classifier = new ProductTaxonomyClassifier;

        $vase = $classifier->classify('Fruit vase KOOPMAN', 'Decor', null);
        $this->assertSame('furniture', $vase['product_family']);
        $this->assertSame('vase', $vase['product_subtype']);

        $pot = $classifier->classify('Casserols FALEZ CREAMY 18CM MILK POT', 'Kitchen', null);
        $this->assertNull($pot['product_family']);
    }

    public function test_qdrant_search_batch_parses_grouped_hits(): void
    {
        config([
            'catalog.qdrant.url' => 'http://qdrant.test',
            'catalog.qdrant.collection' => 'catalog_products_v1',
        ]);

        Http::fake([
            'http://qdrant.test/collections/catalog_products_v1/points/search/batch' => Http::response([
                'result' => [
                    [
                        [
                            'id' => 11,
                            'score' => 0.91,
                            'payload' => ['product_id' => 11, 'product_family' => 'furniture'],
                        ],
                    ],
                    [
                        [
                            'id' => 22,
                            'score' => 0.82,
                            'payload' => ['product_id' => 22, 'product_family' => 'lighting'],
                        ],
                    ],
                ],
            ]),
        ]);

        $client = new QdrantCatalogClient;
        $results = $client->searchBatch([
            [
                'vector' => [0.1, 0.2],
                'limit' => 20,
                'filterMust' => [
                    ['key' => 'product_family', 'match' => ['value' => 'furniture']],
                ],
            ],
            [
                'vector' => [0.1, 0.2],
                'limit' => 20,
                'filterMust' => [
                    ['key' => 'product_family', 'match' => ['value' => 'lighting']],
                ],
            ],
        ]);

        $this->assertCount(2, $results);
        $this->assertSame(11, $results[0][0]['product_id']);
        $this->assertSame(22, $results[1][0]['product_id']);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $this->assertCount(2, $body['searches']);
            $this->assertSame(20, $body['searches'][0]['limit']);
            $this->assertTrue($body['searches'][0]['with_payload']);

            return str_ends_with($request->url(), '/collections/catalog_products_v1/points/search/batch');
        });
    }
}
