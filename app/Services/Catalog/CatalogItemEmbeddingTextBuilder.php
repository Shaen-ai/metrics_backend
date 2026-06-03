<?php

namespace App\Services\Catalog;

use App\Models\CatalogItem;

/**
 * Deterministic embedding_text builder for CatalogItem models.
 */
class CatalogItemEmbeddingTextBuilder
{
    public function version(): string
    {
        return (string) config('catalog.embedding.text_version', 'v1');
    }

    public function build(CatalogItem $item): string
    {
        $maxDesc = (int) config('catalog.embedding.description_max_chars', 400);
        $desc = trim((string) ($item->description ?: ''));
        if (mb_strlen($desc) > $maxDesc) {
            $desc = mb_substr($desc, 0, $maxDesc).'…';
        }

        $w = $item->getWidthCm();
        $d = $item->getDepthCm();
        $h = $item->getHeightCm();
        $dims = ($w !== null)
            ? sprintf('%d×%d×%d cm', (int) round($w), (int) round($d ?? 0), (int) round($h ?? 0))
            : 'dimensions unknown';

        $materials = $this->tagLine($item->material_tags);
        $colors = $this->tagLine($item->color_tags);

        $blockA = implode("\n", array_filter([
            'name: '.trim((string) $item->name),
            'category: '.trim((string) ($item->category ?: 'unknown')),
            'product_family: '.($item->product_family ?: 'unknown'),
            'product_subtype: '.($item->product_subtype ?: 'unknown'),
            $desc !== '' ? 'description: '.$desc : null,
            $materials ? 'material_tags: '.$materials : null,
            $colors ? 'color_tags: '.$colors : null,
            'dimensions: '.$dims,
        ]));

        $blockB = $this->syntheticInteriorPhrasing($item);

        return trim($blockA."\n\n".$blockB);
    }

    /**
     * @param  array<int, string>|null  $tags
     */
    private function tagLine(?array $tags): string
    {
        if (! $tags) {
            return '';
        }

        return implode(', ', array_values(array_filter(array_map('strval', $tags))));
    }

    private function syntheticInteriorPhrasing(CatalogItem $item): string
    {
        $family = (string) ($item->product_family ?: 'furniture');
        $subtype = (string) ($item->product_subtype ?: 'other');
        $ai = is_array($item->ai_tags) ? $item->ai_tags : [];

        $materials = $this->tagLine($ai['materials'] ?? null)
            ?: $this->tagLine($item->material_tags)
            ?: 'mixed materials';
        $colors = $this->tagLine($ai['colors'] ?? null)
            ?: $this->tagLine($item->color_tags)
            ?: 'neutral palette';
        $styles = $this->tagLine($ai['styles'] ?? null) ?: 'modern minimalist';
        $moods = $this->tagLine($ai['moods'] ?? null) ?: 'calm warm';
        $rooms = $this->tagLine($ai['rooms'] ?? null) ?: 'living room';

        $role = $this->subtypeRolePhrase($subtype, $family);
        $familyLabel = match ($family) {
            'home_appliances' => 'home appliance',
            'home_accessories' => 'home accessory',
            default => 'interior piece',
        };

        return implode("\n", [
            sprintf('%s %s.', ucfirst($styles ?: 'modern'), $familyLabel),
            $role,
            $moods ? sprintf('%s atmosphere.', ucfirst($moods)) : '',
            sprintf('%s %s materials.', ucfirst($colors), $materials),
            sprintf('Suitable for %s home design.', str_replace('_', ' ', $rooms)),
        ]);
    }

    private function subtypeRolePhrase(string $subtype, string $family): string
    {
        $map = [
            'sofa' => 'Seating for living room lounge area.',
            'chair' => 'Accent seating for living or dining space.',
            'coffee_table' => 'Low table for living room center layout.',
            'dining_table' => 'Dining table for eating area.',
            'table' => 'Table surface for dining or work zone.',
            'desk' => 'Work desk for home office corner.',
            'bed' => 'Bedroom sleeping furniture piece.',
            'storage' => 'Storage furniture along wall placement.',
            'laminate' => 'Floor covering for living areas.',
            'parquet' => 'Wood floor finish for interior rooms.',
            'tile' => 'Floor or wall tile surface treatment.',
            'rug' => 'Floor textile rug for living area.',
            'carpet' => 'Soft floor carpet textile.',
            'curtain' => 'Window curtain textile treatment.',
            'blind' => 'Window blind covering.',
            'sheer' => 'Sheer window curtain layer.',
            'ceiling' => 'Ceiling lighting fixture for ambient light.',
            'pendant' => 'Pendant light over dining or seating zone.',
            'wall' => 'Wall mounted lighting accent.',
            'floor' => 'Floor lamp for corner ambient light.',
            'wallpaper' => 'Wall surface wallpaper finish.',
            'wall_panel' => 'Decorative wall panel finish.',
            'decorative_plant' => 'Decorative plant or botanical accent for interior styling.',
            'vase' => 'Decorative vase or planter for interior accents.',
        ];

        if (isset($map[$subtype])) {
            return $map[$subtype];
        }

        return match ($family) {
            'flooring' => 'Floor surface treatment for interior rooms.',
            'walls' => 'Wall finish treatment for interior space.',
            'window_treatments' => 'Window textile treatment for natural light control.',
            'lighting' => 'Lighting fixture for room ambiance.',
            'home_appliances' => 'Home appliance for kitchen or utility space.',
            'home_accessories' => 'Decorative or functional home accessory.',
            default => 'Freestanding piece for interior living space.',
        };
    }
}
