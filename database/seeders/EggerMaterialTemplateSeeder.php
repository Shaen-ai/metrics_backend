<?php

namespace Database\Seeders;

use App\Models\MaterialTemplate;
use App\Support\MaterialTypes;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class EggerMaterialTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/egger_user_catalog.json');
        if (! is_readable($path)) {
            throw new \RuntimeException("Missing Egger catalog: {$path}");
        }

        $rows = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($rows)) {
            throw new \RuntimeException('egger_user_catalog.json must be a JSON array.');
        }

        $sort = 0;
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $code = preg_replace('/\s+/', ' ', trim((string) ($row['code'] ?? '')));
            $imageUrl = trim((string) ($row['public_image_url'] ?? $row['image_url'] ?? ''));
            $productType = trim((string) ($row['product_type'] ?? ''));

            if ($name === '' || $code === '' || $imageUrl === '') {
                continue;
            }

            $slug = $this->productTypeSlug($productType);
            $rawTypes = $this->typesForProductType($productType);
            $normalized = MaterialTypes::normalize($rawTypes, $rawTypes[0] ?? 'laminate');
            $primaryType = $normalized[0];

            $id = Uuid::uuid5(
                Uuid::NAMESPACE_URL,
                'mebel:material-template:egger:'.mb_strtoupper($code).':'.$slug
            )->toString();

            $categories = $primaryType === 'worktop'
                ? ['worktop', 'surface']
                : ['surface', 'door'];

            MaterialTemplate::updateOrCreate(
                ['id' => $id],
                [
                    'manufacturer' => 'egger',
                    'external_code' => $code,
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
                    'sort_order' => $sort++,
                ]
            );
        }
    }

    private function productTypeSlug(string $productType): string
    {
        return match (mb_strtolower($productType)) {
            'laminates', 'laminate' => 'laminates',
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
            'laminates', 'laminate' => ['laminate', 'mdf'],
            'worktops', 'worktop' => ['worktop'],
            default => ['laminate', 'mdf'],
        };
    }
}
