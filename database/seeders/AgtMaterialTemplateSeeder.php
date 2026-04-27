<?php

namespace Database\Seeders;

use App\Models\MaterialTemplate;
use App\Support\MaterialTypes;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

/**
 * AGT (agtwood.com) — database/data/agt_catalog.json.
 * Manufacturer slug: agt. Decors apply to both laminate and MDF; sheet size from catalog in cm.
 */
class AgtMaterialTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/agt_catalog.json');
        if (! is_readable($path)) {
            return;
        }

        $rows = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($rows)) {
            throw new \RuntimeException('agt_catalog.json must be a JSON array.');
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

            $id = Uuid::uuid5(
                Uuid::NAMESPACE_URL,
                'mebel:material-template:agt:'.mb_strtoupper($externalCode).':'.$slug.':'.hash('sha256', $fingerprint.':'.$index)
            )->toString();

            $categories = $this->categoriesForPrimaryType($primaryType);
            [$sheetW, $sheetH] = $this->parseSizeMmToCm($row['size'] ?? null);

            MaterialTemplate::updateOrCreate(
                ['id' => $id],
                [
                    'manufacturer' => 'agt',
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
                    'sheet_width_cm' => $sheetW,
                    'sheet_height_cm' => $sheetH,
                    'grain_direction' => null,
                    'kerf_mm' => null,
                    'sort_order' => ++$sort,
                ]
            );
        }
    }

    private function productTypeSlug(string $productType): string
    {
        $lower = mb_strtolower($productType);

        return match (true) {
            $lower === 'mdf and laminate', $lower === 'panels' => 'mdf-laminate',
            default => preg_replace('/\W+/', '-', $lower) ?: 'other',
        };
    }

    /**
     * @return list<string>
     */
    private function typesForProductType(string $productType): array
    {
        return match (mb_strtolower($productType)) {
            'mdf and laminate', 'panels' => ['laminate', 'mdf'],
            default => ['laminate', 'mdf'],
        };
    }

    /**
     * @return list<string>
     */
    private function categoriesForPrimaryType(string $primaryType): array
    {
        return match ($primaryType) {
            'mdf' => ['surface', 'frame'],
            default => ['surface', 'door'],
        };
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function parseSizeMmToCm(null|string $size): array
    {
        $size = trim((string) $size);
        if ($size === '' || ! preg_match('/(\d+(?:\.\d+)?)\s*mm\s*\*\s*(\d+(?:\.\d+)?)\s*mm/i', $size, $m)) {
            return [null, null];
        }

        return [(float) $m[1] / 10.0, (float) $m[2] / 10.0];
    }
}
