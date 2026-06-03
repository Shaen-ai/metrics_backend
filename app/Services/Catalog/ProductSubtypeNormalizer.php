<?php

namespace App\Services\Catalog;

/**
 * Map free-form slot subtypes (from Claude briefs) to canonical catalog taxonomy slugs.
 */
class ProductSubtypeNormalizer
{
    /** @var array<string, string> raw token → canonical subtype */
    private const ALIASES = [
        'armchair' => 'chair',
        'accent_chair' => 'chair',
        'accent chair' => 'chair',
        'recliner' => 'chair',
        'lounge_chair' => 'chair',
        'lounge chair' => 'chair',
        'dining_chair' => 'chair',
        'dining chair' => 'chair',
        'stool' => 'chair',
        'bar_stool' => 'chair',
        'bar stool' => 'chair',
        'coffee table' => 'coffee_table',
        'coffee_table' => 'coffee_table',
        'side table' => 'coffee_table',
        'side_table' => 'coffee_table',
        'console table' => 'coffee_table',
        'console_table' => 'coffee_table',
        'dining table' => 'dining_table',
        'dining_table' => 'dining_table',
        'tv stand' => 'tv_stand',
        'tv_stand' => 'tv_stand',
        'media unit' => 'tv_stand',
        'media_unit' => 'tv_stand',
        'media_console' => 'tv_stand',
        'media_console_low' => 'tv_stand',
        'low_media_console' => 'tv_stand',
        'storage_ottoman' => 'storage',
        'ottoman' => 'storage',
        'sheer_linen_panels' => 'sheer',
        'sheer_linen' => 'sheer',
        'linen_sheer_panels' => 'sheer',
        'accent_wall_paint' => 'wallpaper',
        'accent_wall' => 'wallpaper',
        'wall_paint' => 'wallpaper',
        'area rug' => 'rug',
        'area_rug' => 'rug',
        'carpet' => 'carpet',
        'rug' => 'rug',
        'chandelier' => 'ceiling',
        'pendant ceiling light' => 'pendant',
        'pendant_ceiling_light' => 'pendant',
        'pendant light' => 'pendant',
        'pendant_light' => 'pendant',
        'arc floor lamp' => 'floor',
        'arc_floor_lamp' => 'floor',
        'floor lamp' => 'floor',
        'floor_lamp' => 'floor',
        'table lamp' => 'table',
        'table_lamp' => 'table',
        'ceiling light' => 'ceiling',
        'ceiling_light' => 'ceiling',
        'floor-length drape curtains' => 'curtain',
        'floor_length_drape_curtains' => 'curtain',
        'drape curtains' => 'curtain',
        'drape_curtains' => 'curtain',
        'drapes' => 'curtain',
        'drape' => 'curtain',
        'curtains' => 'curtain',
        'curtain' => 'curtain',
        'blinds' => 'blind',
        'blind' => 'blind',
        'accent wall finish' => 'wallpaper',
        'accent_wall_finish' => 'wallpaper',
        'wall panel' => 'wall_panel',
        'wall_panel' => 'wall_panel',
        'wallpaper' => 'wallpaper',
        'decorative planter with plant' => 'vase',
        'decorative_planter_with_plant' => 'vase',
        'planter' => 'vase',
        'vase' => 'vase',
        'plant_stand' => 'plant_stand',
        'plant stand' => 'plant_stand',
        'laminate' => 'laminate',
        'parquet' => 'parquet',
        'tile' => 'tile',
        'vinyl' => 'vinyl',
        'sofa' => 'sofa',
        'bed' => 'bed',
        'desk' => 'desk',
        'wardrobe' => 'wardrobe',
        'storage' => 'storage',
        'chair' => 'chair',
        'table' => 'table',
        'ceiling' => 'ceiling',
        'pendant' => 'pendant',
        'floor' => 'floor',
        'wall' => 'wall',
        'sheer' => 'sheer',
    ];

    public static function normalize(?string $family, ?string $subtype): ?string
    {
        if ($subtype === null || trim($subtype) === '' || strtolower(trim($subtype)) === 'other') {
            return null;
        }

        $raw = mb_strtolower(trim($subtype));
        $raw = str_replace(['-', ' '], '_', $raw);
        $raw = preg_replace('/_+/', '_', $raw) ?? $raw;

        if (isset(self::ALIASES[$raw])) {
            return self::ALIASES[$raw];
        }

        // Try with spaces restored for multi-word aliases stored with spaces
        $withSpaces = str_replace('_', ' ', $raw);
        if (isset(self::ALIASES[$withSpaces])) {
            return self::ALIASES[$withSpaces];
        }

        // Family-specific hints
        if ($family === 'lighting') {
            if (str_contains($raw, 'chandelier') || str_contains($raw, 'ceiling')) {
                return 'ceiling';
            }
            if (str_contains($raw, 'pendant')) {
                return 'pendant';
            }
            if (str_contains($raw, 'floor') && str_contains($raw, 'lamp')) {
                return 'floor';
            }
            if (str_contains($raw, 'table') && str_contains($raw, 'lamp')) {
                return 'table';
            }
        }

        if ($family === 'window_treatments') {
            if (str_contains($raw, 'sheer')) {
                return 'sheer';
            }
            if (str_contains($raw, 'curtain') || str_contains($raw, 'drape')) {
                return 'curtain';
            }
            if (str_contains($raw, 'blind')) {
                return 'blind';
            }
        }

        if ($family === 'walls') {
            if (str_contains($raw, 'wallpaper') || str_contains($raw, 'wall_paper')) {
                return 'wallpaper';
            }
            if (str_contains($raw, 'panel')) {
                return 'wall_panel';
            }
            if (str_contains($raw, 'paint') || str_contains($raw, 'accent_wall')) {
                return 'wallpaper';
            }
        }

        if ($family === 'flooring') {
            if (str_contains($raw, 'rug') || str_contains($raw, 'carpet')) {
                return str_contains($raw, 'carpet') ? 'carpet' : 'rug';
            }
            if (str_contains($raw, 'laminate') || str_contains($raw, 'parquet')) {
                return str_contains($raw, 'parquet') ? 'parquet' : 'laminate';
            }
            if (str_contains($raw, 'tile')) {
                return 'tile';
            }
        }

        if ($family === 'furniture') {
            if (str_contains($raw, 'coffee') && str_contains($raw, 'table')) {
                return 'coffee_table';
            }
            if (str_contains($raw, 'side') && str_contains($raw, 'table')) {
                return 'coffee_table';
            }
            if (str_contains($raw, 'tv') && str_contains($raw, 'stand')) {
                return 'tv_stand';
            }
            if (str_contains($raw, 'armchair') || $raw === 'chair') {
                return 'chair';
            }
            if (str_contains($raw, 'sofa') || str_contains($raw, 'sectional')) {
                return 'sofa';
            }
            if (str_contains($raw, 'ottoman')) {
                return 'storage';
            }
            if (str_contains($raw, 'media') && str_contains($raw, 'console')) {
                return 'tv_stand';
            }
        }

        return $raw;
    }

    /**
     * True when the product has a known subtype that disagrees with the slot (not when taxonomy is missing).
     */
    public static function subtypeConflictsWithSlot(?string $family, ?string $slotSubtype, ?string $productSubtype): bool
    {
        $slot = self::normalize($family, $slotSubtype);
        if ($slot === null || $slot === '') {
            return false;
        }

        $product = self::normalize($family, $productSubtype);
        if ($product === null || $product === '') {
            return false;
        }

        return $slot !== $product;
    }

    public static function subtypesMatch(?string $family, ?string $slotSubtype, ?string $productSubtype): bool
    {
        $a = self::normalize($family, $slotSubtype);
        $b = self::normalize($family, $productSubtype);

        if ($a === null || $b === null) {
            return false;
        }

        return $a === $b;
    }
}
