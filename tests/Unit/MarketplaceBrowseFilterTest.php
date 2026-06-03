<?php

namespace Tests\Unit;

use App\Models\ScrapedProduct;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;
use Tests\TestCase;

class MarketplaceBrowseFilterTest extends TestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('scraped_products')) {
            Schema::create('scraped_products', function ($table) {
                $table->id();
                $table->string('source_marketplace', 32)->index();
                $table->string('external_url', 1024)->nullable();
                $table->string('external_url_hash', 64)->nullable();
                $table->string('slug', 512)->nullable();
                $table->string('name', 512);
                $table->string('name_en', 512)->nullable();
                $table->string('category', 256)->nullable();
                $table->string('category_en', 256)->nullable();
                $table->string('brand', 128)->nullable();
                $table->string('sku', 128)->nullable();
                $table->unsignedInteger('price')->default(0);
                $table->string('currency', 8)->default('AMD');
                $table->unsignedInteger('old_price')->nullable();
                $table->boolean('in_stock')->default(true);
                $table->unsignedInteger('priority')->nullable();
                $table->float('width_cm')->nullable();
                $table->float('depth_cm')->nullable();
                $table->float('height_cm')->nullable();
                $table->boolean('has_dimensions')->default(false);
                $table->string('main_image_url', 1024)->nullable();
                $table->json('images')->nullable();
                $table->string('product_family', 48)->nullable();
                $table->string('product_subtype', 48)->nullable();
                $table->timestamp('unavailable_at')->nullable();
                $table->timestamps();
            });
        }

        ScrapedProduct::query()->delete();
    }

    private function createProduct(array $attrs): ScrapedProduct
    {
        return ScrapedProduct::create(array_merge([
            'source_marketplace' => 'vega',
            'external_url' => 'https://example.com/'.uniqid(),
            'external_url_hash' => hash('sha256', uniqid()),
            'name' => 'Product',
            'price' => 10000,
            'in_stock' => true,
        ], $attrs));
    }

    public function test_browse_with_product_subtypes_filters_multiple(): void
    {
        $this->createProduct(['name' => 'Coffee Table A', 'product_family' => 'furniture', 'product_subtype' => 'coffee_table']);
        $this->createProduct(['name' => 'Dining Table B', 'product_family' => 'furniture', 'product_subtype' => 'dining_table']);
        $this->createProduct(['name' => 'Desk C', 'product_family' => 'furniture', 'product_subtype' => 'desk']);
        $this->createProduct(['name' => 'Sofa D', 'product_family' => 'furniture', 'product_subtype' => 'sofa']);
        $this->createProduct(['name' => 'Chair E', 'product_family' => 'furniture', 'product_subtype' => 'chair']);

        $response = $this->getJson('/api/marketplace/products/browse?product_subtypes=coffee_table,dining_table,desk');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $names = array_column($data, 'name');
        $this->assertContains('Coffee Table A', $names);
        $this->assertContains('Dining Table B', $names);
        $this->assertContains('Desk C', $names);
        $this->assertNotContains('Sofa D', $names);
        $this->assertNotContains('Chair E', $names);
    }

    public function test_browse_product_subtypes_combines_with_product_family(): void
    {
        $this->createProduct(['name' => 'Floor Lamp', 'product_family' => 'lighting', 'product_subtype' => 'floor']);
        $this->createProduct(['name' => 'Pendant', 'product_family' => 'lighting', 'product_subtype' => 'ceiling']);
        $this->createProduct(['name' => 'Sofa X', 'product_family' => 'furniture', 'product_subtype' => 'sofa']);

        $response = $this->getJson('/api/marketplace/products/browse?product_family=lighting&product_subtypes=floor,ceiling');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $names = array_column($data, 'name');
        $this->assertContains('Floor Lamp', $names);
        $this->assertContains('Pendant', $names);
    }

    public function test_browse_single_product_subtype_still_works(): void
    {
        $this->createProduct(['name' => 'Sofa A', 'product_family' => 'furniture', 'product_subtype' => 'sofa']);
        $this->createProduct(['name' => 'Chair B', 'product_family' => 'furniture', 'product_subtype' => 'chair']);

        $response = $this->getJson('/api/marketplace/products/browse?product_subtype=sofa');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Sofa A', $data[0]['name']);
    }

    public function test_browse_product_subtypes_takes_precedence_over_product_subtype(): void
    {
        $this->createProduct(['name' => 'Sofa A', 'product_family' => 'furniture', 'product_subtype' => 'sofa']);
        $this->createProduct(['name' => 'Chair B', 'product_family' => 'furniture', 'product_subtype' => 'chair']);
        $this->createProduct(['name' => 'Desk C', 'product_family' => 'furniture', 'product_subtype' => 'desk']);

        $response = $this->getJson('/api/marketplace/products/browse?product_subtypes=sofa,desk&product_subtype=chair');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $names = array_column($data, 'name');
        $this->assertContains('Sofa A', $names);
        $this->assertContains('Desk C', $names);
        $this->assertNotContains('Chair B', $names);
    }
}
