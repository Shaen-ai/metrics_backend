<?php

namespace App\Services\Catalog;

/**
 * Scraped marketplace products that must never appear in design pools or catalog browse.
 *
 * - isExcluded(): hard purge — delete from DB and Qdrant, skip on future scrapes
 * - isCatalogHidden(): keep in DB with null product_family, hide from browse/search, no Qdrant embed
 */
class ExcludedScrapedProduct
{
    public static function isExcluded(
        ?string $name,
        ?string $nameEn = null,
        ?string $category = null,
        ?string $categoryEn = null,
    ): bool {
        $nameHay = self::hay($name, $nameEn);

        if (self::isHardExcludedByName($nameHay)) {
            return true;
        }

        if (self::isCatalogHidden($name, $nameEn, $category, $categoryEn)) {
            return false;
        }

        $hay = self::hay($name, $nameEn, $category, $categoryEn);

        return self::isSoftGood($hay) || self::isGenericLamp($nameHay, $category, $categoryEn);
    }

    public static function isCatalogHidden(
        ?string $name,
        ?string $nameEn = null,
        ?string $category = null,
        ?string $categoryEn = null,
    ): bool {
        $nameHay = self::hay($name, $nameEn);

        if (self::isHardExcludedByName($nameHay)) {
            return false;
        }

        $hay = self::hay($name, $nameEn, $category, $categoryEn);

        if (self::matches($hay, [
            'towel rail', 'towel bar', 'towel radiator', 'towel holder',
            'полотенцедержатель', 'держатель для полотенец',
        ])) {
            return true;
        }

        return self::matches($nameHay, [
            'դեկոր', 'երիզ', 'մոզաիկա',
            'table lamp eivin',
            'Պատի սալիկ', 'настенная плитка',
            'decorate objects koopman',
            'carpet', 'fitted sheet', 'tablecloth', 'hotel textile', 'kitchen holders', 'wallpaper',
        ]);
    }

    private static function isHardExcludedByName(string $nameHay): bool
    {
        return self::matches($nameHay, [
            'n/y decorate objects koopman santa on',
            'decorate objects koopman santa',
        ]);
    }

    private static function isSoftGood(string $hay): bool
    {
        if (self::matches($hay, [
            'apron', 'apron set',
            'bathrobe', 'bath robe', 'dressing gown',
            'dishcloth', 'dish cloth', 'oven mitt', 'oven glove', 'pot holder',
            'blanket', 'plaid', 'duvet', 'comforter', 'bedding', 'bed sheet', 'bed linen',
            'pillow', 'cushion',
            'bath mat', 'bathtub mat', 'bath rug', 'shower mat', 'bathroom mat',
        ])) {
            return true;
        }

        if (preg_match('/\bthrow\b/ui', $hay)) {
            return true;
        }

        return self::isTowelProduct($hay);
    }

    private static function isTowelProduct(string $hay): bool
    {
        if (! self::matches($hay, [
            'towel', 'towels', 'полотенц',
        ])) {
            return false;
        }

        return ! self::matches($hay, [
            'towel rail', 'towel bar', 'towel radiator', 'towel holder',
            'полотенцедержатель', 'держатель для полотенец',
        ]);
    }

    private static function isGenericLamp(string $nameHay, ?string $category = null, ?string $categoryEn = null): bool
    {
        if ($nameHay === '') {
            return false;
        }

        if (self::isKeptLighting($nameHay)) {
            return false;
        }

        if (self::matches($nameHay, ['flashlight', 'headlamp', 'head lamp', 'torch '])) {
            return false;
        }

        if (self::matches($nameHay, ['floor lamp', 'floor light', 'floor_lamp'])) {
            return true;
        }

        $categoryHay = self::hay(null, null, $category, $categoryEn);
        if ($categoryHay !== ''
            && self::matches($categoryHay, ['լուսատուն', 'Լուսատուն'])
            && ! self::matches($categoryHay, ['սեղանի', 'lampshade', 'lamp shade', 'լուսամփոփ', 'Լուսամփոփ'])
            && ! self::isKeptLighting($nameHay)
        ) {
            return true;
        }

        return (bool) preg_match('/\blamps?\b/ui', $nameHay);
    }

    private static function isKeptLighting(string $hay): bool
    {
        return self::matches($hay, [
            'chandelier', 'люстр', 'ջահեր',
            'lampshade', 'lamp shade', 'shade only', 'լուսամփոփ',
            'table lamp', 'table light', 'desk lamp', 'սեղանի լուսատուն',
            'battery lamp', 'battery-powered', 'battery powered', 'cordless lamp',
            'rechargeable lamp', 'led battery',
            'sconce', 'spotlight', 'track light', 'wall light', 'ceiling light',
            'luminaire', 'светильник', 'ճաղաշarք',
            'pendant',
        ]);
    }

    private static function hay(
        ?string $name,
        ?string $nameEn = null,
        ?string $category = null,
        ?string $categoryEn = null,
    ): string {
        return mb_strtolower(trim(implode(' ', array_filter([
            $name,
            $nameEn,
            $category,
            $categoryEn,
        ], fn ($v) => $v !== null && $v !== ''))));
    }

    /**
     * @param  list<string>  $needles
     */
    private static function matches(string $hay, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($hay, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
