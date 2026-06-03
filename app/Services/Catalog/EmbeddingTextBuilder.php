<?php

namespace App\Services\Catalog;

use App\Models\ScrapedProduct;

/**
 * Deterministic embedding_text DSL (EmbeddingTextBuilder v1).
 */
class EmbeddingTextBuilder
{
    public function version(): string
    {
        return (string) config('catalog.embedding.text_version', 'v1');
    }

    public function build(ScrapedProduct $product): string
    {
        $maxDesc = (int) config('catalog.embedding.description_max_chars', 400);
        $desc = trim((string) ($product->description_en ?: $product->description ?: ''));
        if (mb_strlen($desc) > $maxDesc) {
            $desc = mb_substr($desc, 0, $maxDesc).'…';
        }

        $dims = $product->has_dimensions && $product->width_cm
            ? sprintf('%d×%d×%d cm', $product->width_cm, $product->depth_cm ?? 0, $product->height_cm ?? 0)
            : 'dimensions unknown';

        $materials = $this->tagLine($product->material_tags);
        $colors = $this->tagLine($product->color_tags);

        $blockA = implode("\n", array_filter([
            'name: '.trim((string) $product->name),
            $product->name_en ? 'name_en: '.trim((string) $product->name_en) : null,
            $product->category_en ? 'category_en: '.trim((string) $product->category_en) : null,
            $desc !== '' ? 'description: '.$desc : null,
            'product_family: '.($product->product_family ?: 'unknown'),
            'product_subtype: '.($product->product_subtype ?: 'unknown'),
            $materials ? 'material_tags: '.$materials : null,
            $colors ? 'color_tags: '.$colors : null,
            'dimensions: '.$dims,
        ]));

        $blockB = $this->syntheticInteriorPhrasing($product);

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

    private function syntheticInteriorPhrasing(ScrapedProduct $product): string
    {
        $family = (string) ($product->product_family ?: 'furniture');
        $subtype = (string) ($product->product_subtype ?: 'other');
        $ai = is_array($product->ai_tags) ? $product->ai_tags : [];

        $materials = $this->tagLine($ai['materials'] ?? null)
            ?: $this->tagLine($product->material_tags)
            ?: 'mixed materials';
        $colors = $this->tagLine($ai['colors'] ?? null)
            ?: $this->tagLine($product->color_tags)
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
