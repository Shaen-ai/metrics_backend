<?php

namespace Tests;

use App\Models\ScrapedProduct;
use App\Services\Catalog\ProductTaxonomyAudit;
use App\Services\Catalog\ProductTaxonomyClassifier;

class ProductTaxonomyAuditTest extends TestCase
{
    use CreatesApplication;

    private ProductTaxonomyAudit $audit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->audit = new ProductTaxonomyAudit(new ProductTaxonomyClassifier);
    }

    public function test_clean_row_returns_no_anomaly(): void
    {
        $product = new ScrapedProduct([
            'name' => 'Sofa HOBEL CORNER WOOD GREY',
            'category_en' => 'Living room > Sofa',
            'product_family' => 'furniture',
            'product_subtype' => 'sofa',
        ]);
        $product->id = 1;

        $this->assertNull($this->audit->checkRow($product));
    }

    public function test_name_family_mismatch_detected(): void
    {
        $product = new ScrapedProduct([
            'name' => 'Sofa HOBEL DANIA 3 STR OAK LIGHT GREY',
            'category_en' => 'Ceiling lights',
            'product_family' => 'lighting',
            'product_subtype' => 'ceiling',
        ]);
        $product->id = 13925;

        $anomaly = $this->audit->checkRow($product);

        $this->assertNotNull($anomaly);
        $this->assertSame('name_family_mismatch', $anomaly['reason']);
        $this->assertSame('lighting', $anomaly['stored_family']);
        $this->assertSame('furniture', $anomaly['expected_family']);
        $this->assertSame('sofa', $anomaly['expected_subtype']);
    }

    public function test_invalid_pair_detected(): void
    {
        $product = new ScrapedProduct([
            'name' => 'Some random product XYZ',
            'category_en' => 'Decor',
            'product_family' => 'furniture',
            'product_subtype' => 'ceiling',
        ]);
        $product->id = 999;

        $anomaly = $this->audit->checkRow($product);

        $this->assertNotNull($anomaly);
        $this->assertSame('invalid_pair', $anomaly['reason']);
    }

    public function test_drift_detected_when_subtype_changed(): void
    {
        $product = new ScrapedProduct([
            'name' => 'Floor lamp KENT #30xH140cm beige',
            'category_en' => 'Lighting',
            'product_family' => 'lighting',
            'product_subtype' => 'ceiling',
        ]);
        $product->id = 500;

        $anomaly = $this->audit->checkRow($product);

        $this->assertNotNull($anomaly);
        $this->assertSame('drift', $anomaly['reason']);
        $this->assertSame('floor', $anomaly['expected_subtype']);
    }

    public function test_anomaly_for_payload_catches_scrape_time_mismatch(): void
    {
        $anomaly = ProductTaxonomyAudit::anomalyForPayload(
            'Sofa HOBEL CORNER LUXE',
            'Ceiling lights',
            'lighting',
            'ceiling',
        );

        $this->assertNotNull($anomaly);
        $this->assertSame('name_family_mismatch', $anomaly['reason']);
        $this->assertSame('furniture', $anomaly['expected_family']);
        $this->assertSame('sofa', $anomaly['expected_subtype']);
    }

    public function test_anomaly_for_payload_returns_null_when_ok(): void
    {
        $anomaly = ProductTaxonomyAudit::anomalyForPayload(
            'Floor lamp KENT beige',
            'Lighting',
            'lighting',
            'floor',
        );

        $this->assertNull($anomaly);
    }
}
