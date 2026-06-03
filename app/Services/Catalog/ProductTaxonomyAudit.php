<?php

namespace App\Services\Catalog;

use App\Models\ScrapedProduct;
use Illuminate\Database\Eloquent\Builder;

/**
 * Detects scraped_products rows whose stored product_family / product_subtype
 * disagrees with what the classifier would produce today.
 */
class ProductTaxonomyAudit
{
    /** Family → allowed subtypes. Subtypes not in this map are flagged as invalid_pair. */
    private const VALID_PAIRS = [
        'furniture' => [
            'sofa', 'chair', 'coffee_table', 'dining_table', 'desk', 'bed',
            'storage', 'tv_stand', 'vase', 'plant_stand', 'decorative_plant',
            'tv', 'wardrobe', null,
        ],
        'flooring' => ['laminate', 'parquet', 'tile', 'vinyl', 'rug', 'carpet', 'bath_mat', null],
        'lighting' => ['ceiling', 'pendant', 'floor', 'table', 'wall', null],
        'window_treatments' => ['curtain', 'blind', 'sheer', null],
        'walls' => ['wallpaper', 'wall_panel', 'door', 'door_handle', 'mirror', 'tv_mount', 'sink', null],
        'home_appliances' => [
            'refrigerator', 'washing_machine', 'dishwasher', 'dryer',
            'oven', 'hob', 'hood', 'freezer', 'microwave', 'cooker', null,
        ],
        'home_accessories' => [null],
    ];

    public function __construct(
        private readonly ProductTaxonomyClassifier $classifier,
    ) {}

    /**
     * Check a single row. Returns null if OK, or an anomaly array.
     *
     * @return array{product_id: int, name: string, category_en: ?string, stored_family: ?string, stored_subtype: ?string, expected_family: ?string, expected_subtype: ?string, reason: string}|null
     */
    public function checkRow(ScrapedProduct $product): ?array
    {
        $expected = $this->classifier->expectedClassification(
            (string) $product->name,
            $product->category_en,
            $product->category,
        );

        $storedFamily = $product->product_family;
        $storedSubtype = $product->product_subtype;
        $expectedFamily = $expected['product_family'];
        $expectedSubtype = $expected['product_subtype'];

        $base = [
            'product_id' => $product->id,
            'name' => (string) $product->name,
            'category_en' => $product->category_en,
            'stored_family' => $storedFamily,
            'stored_subtype' => $storedSubtype,
            'expected_family' => $expectedFamily,
            'expected_subtype' => $expectedSubtype,
        ];

        // Check 1: name-family mismatch (highest confidence — name clearly says X, DB says Y)
        $nameFirst = $this->classifier->classifyFromName((string) $product->name);
        if ($nameFirst !== null
            && $storedFamily !== null
            && $storedFamily !== $nameFirst['product_family']
        ) {
            return array_merge($base, ['reason' => 'name_family_mismatch']);
        }

        // Check 2: invalid family/subtype pair
        if ($storedFamily !== null && $storedSubtype !== null) {
            $allowed = self::VALID_PAIRS[$storedFamily] ?? null;
            if ($allowed !== null && ! in_array($storedSubtype, $allowed, true)) {
                return array_merge($base, ['reason' => 'invalid_pair']);
            }
        }

        // Check 3: drift — classifier would produce different family or subtype
        if ($expectedFamily !== null && $storedFamily !== null) {
            $familyNorm = ProductSubtypeNormalizer::normalize($storedFamily, $storedSubtype);
            $expectedNorm = ProductSubtypeNormalizer::normalize($expectedFamily, $expectedSubtype);

            $familyDrift = $storedFamily !== $expectedFamily;
            $subtypeDrift = $familyNorm !== $expectedNorm;

            if ($familyDrift || $subtypeDrift) {
                return array_merge($base, ['reason' => 'drift']);
            }
        }

        return null;
    }

    /**
     * Lightweight single-payload check (used at scrape time before DB write).
     *
     * @return array{reason: string, expected_family: string, expected_subtype: string|null}|null
     */
    public static function anomalyForPayload(string $name, ?string $categoryEn, ?string $storedFamily, ?string $storedSubtype): ?array
    {
        $classifier = app(ProductTaxonomyClassifier::class);
        $nameFirst = $classifier->classifyFromName($name);
        if ($nameFirst === null) {
            return null;
        }
        if ($storedFamily !== null && $storedFamily !== $nameFirst['product_family']) {
            return [
                'reason' => 'name_family_mismatch',
                'expected_family' => $nameFirst['product_family'],
                'expected_subtype' => $nameFirst['product_subtype'],
            ];
        }

        return null;
    }

    /**
     * Scan a query of ScrapedProducts, yielding anomalies.
     *
     * @return array{anomalies: list<array>, total_scanned: int}
     */
    public function scan(Builder $query, int $limit = 0): array
    {
        $anomalies = [];
        $total = 0;
        $q = (clone $query)->orderBy('id');
        if ($limit > 0) {
            $q->limit($limit);
        }

        $q->chunkById(200, function ($rows) use (&$anomalies, &$total) {
            foreach ($rows as $product) {
                $total++;
                $anomaly = $this->checkRow($product);
                if ($anomaly !== null) {
                    $anomalies[] = $anomaly;
                }
            }
        });

        return ['anomalies' => $anomalies, 'total_scanned' => $total];
    }
}
