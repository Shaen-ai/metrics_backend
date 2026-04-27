<?php

namespace Database\Seeders;

use App\Models\MaterialTemplate;
use App\Support\MaterialTypes;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

/**
 * Shop Domus (Armenia) — Decora catalog: domus_shop_catalog.json (worktops),
 * domus_laminates_catalog.json, domus_mdf_catalog.json (merged in order).
 * Stable IDs use name + image URL + article code so duplicate codes stay distinct.
 */
class DomusMaterialTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];
        foreach (['domus_shop_catalog.json', 'domus_laminates_catalog.json', 'domus_mdf_catalog.json'] as $file) {
            $path = database_path('data/'.$file);
            if (! is_readable($path)) {
                continue;
            }
            $part = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($part)) {
                throw new \RuntimeException($file.' must be a JSON array.');
            }
            $rows = array_merge($rows, $part);
        }

        if ($rows === []) {
            return;
        }

        $sort = (int) (MaterialTemplate::query()->max('sort_order') ?? 0);

        foreach ($rows as $row) {
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
                'mebel:material-template:domus:'.mb_strtoupper($externalCode).':'.$slug.':'.hash('sha256', $fingerprint)
            )->toString();

            $categories = $this->categoriesForProductType($primaryType);

            MaterialTemplate::updateOrCreate(
                ['id' => $id],
                [
                    'manufacturer' => 'domus',
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
            'laminates', 'laminate', 'laminate panels' => 'laminates',
            'mdf', 'mdf panels' => 'mdf',
            'worktops', 'worktop' => 'worktops',
            default => preg_replace('/\W+/', '-', mb_strtolower($productType)) ?: 'other',
        };
    }

    /**
     * @return list<string>
     */
    private function typesForProductType(string $productType): array
    {
        return match (mb_strtolower($productType)) {
            'laminates', 'laminate', 'laminate panels' => ['laminate', 'mdf'],
            'mdf', 'mdf panels' => ['mdf'],
            'worktops', 'worktop' => ['worktop'],
            default => ['laminate', 'mdf'],
        };
    }

    /**
     * First category is the Domus tag; remaining entries are functional roles.
     *
     * @return list<string>
     */
    private function categoriesForProductType(string $primaryType): array
    {
        return match ($primaryType) {
            'worktop' => ['domus', 'worktop', 'surface'],
            'mdf' => ['domus', 'surface', 'frame'],
            default => ['domus', 'surface', 'door'],
        };
    }
}
