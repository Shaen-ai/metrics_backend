<?php

namespace Database\Seeders;

use App\Models\MaterialTemplate;
use App\Support\MaterialTypes;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

/**
 * Kastamonu (kastamonuentegre.com) — database/data/kastamonu_catalog.json.
 * Manufacturer slug: kastamonu (same grouping as Egger in the catalog UI).
 * Row index is part of the stable ID so duplicate name/code/image lines stay distinct rows.
 */
class KastamonuMaterialTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/kastamonu_catalog.json');
        if (! is_readable($path)) {
            return;
        }

        $rows = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($rows)) {
            throw new \RuntimeException('kastamonu_catalog.json must be a JSON array.');
        }

        $sort = (int) (MaterialTemplate::query()->max('sort_order') ?? 0);

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $code = preg_replace('/\s+/', ' ', trim((string) ($row['code'] ?? '')));
            $imageUrl = trim((string) ($row['public_image_url'] ?? $row['image_url'] ?? ''));
            $productType = trim((string) ($row['product_type'] ?? ''));

            if ($name === '' || $imageUrl === '') {
                continue;
            }

            $fingerprint = $name.'|'.$imageUrl.'|'.$code;
            $externalCode = $code !== ''
                ? $code
                : 'AUTO-'.substr(hash('sha256', $fingerprint), 0, 16);

            $slug = $this->productTypeSlug($productType);
            $rawTypes = $this->typesForProductType($productType);
            $normalized = MaterialTypes::normalize($rawTypes, $rawTypes[0] ?? 'laminate');
            $primaryType = $normalized[0];

            // Include row index so identical rows in the source file get distinct template IDs.
            $id = Uuid::uuid5(
                Uuid::NAMESPACE_URL,
                'mebel:material-template:kastamonu:'.mb_strtoupper($externalCode).':'.$slug.':'.hash('sha256', $fingerprint.':'.$index)
            )->toString();

            $categories = $this->categoriesForPrimaryType($primaryType);

            MaterialTemplate::updateOrCreate(
                ['id' => $id],
                [
                    'manufacturer' => 'kastamonu',
                    'external_code' => $externalCode,
                    'name' => $name,
                    'type' => $primaryType,
                    'types' => $normalized,
                    'categories' => $categories,
                    'category' => $categories[0],
                    'color' => mb_substr($name, 0, 191),
                    'color_hex' => null,
                    'color_code' => null,
                    'unit' => 'sqm',
                    'image_url' => $imageUrl,
                    'source_url' => null,
                    'sheet_width_cm' => null,
                    'sheet_height_cm' => null,
                    'grain_direction' => null,
                    'kerf_mm' => null,
                    'sort_order' => ++$sort,
                ]
            );
        }
    }

    private function productTypeSlug(string $productType): string
    {
        return match (mb_strtolower($productType)) {
            'acrylic panel' => 'acrylic',
            'lacquered panel' => 'lacquered',
            'melamine faced compact panel' => 'mf-compact',
            'melamine faced panel' => 'mf-panel',
            'painted panel' => 'painted',
            'pvc-pet panel' => 'pvc-pet',
            default => preg_replace('/\W+/', '-', mb_strtolower($productType)) ?: 'other',
        };
    }

    /**
     * @return list<string>
     */
    private function typesForProductType(string $productType): array
    {
        return match (mb_strtolower($productType)) {
            // Single-type so primary is plastic (normalize() would otherwise prefer laminate over plastic).
            'pvc-pet panel' => ['plastic'],
            'acrylic panel', 'lacquered panel', 'melamine faced compact panel', 'melamine faced panel', 'painted panel' => ['laminate', 'mdf'],
            default => ['laminate', 'mdf'],
        };
    }

    /**
     * @return list<string>
     */
    private function categoriesForPrimaryType(string $primaryType): array
    {
        return match ($primaryType) {
            'plastic' => ['surface', 'door'],
            'mdf' => ['surface', 'frame'],
            default => ['surface', 'door'],
        };
    }
}
