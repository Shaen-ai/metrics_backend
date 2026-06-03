<?php

namespace App\Services\Catalog;

/**
 * Rule-based product_family / product_subtype for scraped_products rows.
 *
 * Name-derived signals (sofa, bed, dining table ...) take priority over
 * category-derived signals to prevent misclassification when a merchant
 * places a product in the wrong aisle (e.g. "Sofa HOBEL" in "Ceiling lights").
 */
class ProductTaxonomyClassifier
{
    /**
     * Strong name-only classification for high-confidence product types.
     *
     * Runs against the product name alone (without category) so that a wrong
     * merchant category cannot override an obvious furniture signal.
     *
     * @return array{product_family: string, product_subtype: string|null}|null
     */
    public function classifyFromName(string $name): ?array
    {
        $nameHay = mb_strtolower(trim($name));

        // Exclusions: kitchen/cookware names should not fall through to furniture
        if ($this->matches($nameHay, [
            'casserole', 'casserol', 'milk pot', 'saucepan', 'frying pan', 'cookware',
            'kitchen pot', 'stock pot', 'tea pot', 'teapot', 'kettle', 'cook pot',
        ]) && ! $this->matches($nameHay, ['coffee table', 'side table', 'dining table'])) {
            return null;
        }

        // Tealights / candle holders are decor, not lighting
        if ($this->matches($nameHay, ['tealight', 'candle holder', 'candle stick', 'candlestick', 'candle lantern'])) {
            return ['product_family' => 'home_accessories', 'product_subtype' => null];
        }

        // Flashlights / torches are accessories, not lighting fixtures
        if ($this->matches($nameHay, ['flashlight', 'torch ', 'headlamp', 'head lamp'])) {
            return ['product_family' => 'home_accessories', 'product_subtype' => null];
        }

        if ($this->matches($nameHay, ['tv stand', 'tv_stand', 'tv unit', 'tv_unit', 'media unit', 'media console', 'tv bench'])) {
            return ['product_family' => 'furniture', 'product_subtype' => 'tv_stand'];
        }
        if ($this->matches($nameHay, ['coffee table', 'side table', 'end table', 'console table'])) {
            return ['product_family' => 'furniture', 'product_subtype' => 'coffee_table'];
        }
        if ($this->matches($nameHay, ['dining table', 'dining_table'])) {
            return ['product_family' => 'furniture', 'product_subtype' => 'dining_table'];
        }
        if ($this->matches($nameHay, ['sofa', 'sectional', 'divan'])) {
            return ['product_family' => 'furniture', 'product_subtype' => 'sofa'];
        }
        if ($this->matches($nameHay, ['bed', 'mattress'])
            && ! $this->matches($nameHay, ['bed linen', 'bedding', 'pillow'])
        ) {
            return ['product_family' => 'furniture', 'product_subtype' => 'bed'];
        }
        if ($this->isBedTextileProduct($nameHay)) {
            return ['product_family' => 'home_accessories', 'product_subtype' => null];
        }
        if ($this->isSeasonalSoftDecor($nameHay)) {
            return ['product_family' => 'home_accessories', 'product_subtype' => null];
        }
        if ($this->isDecorativeCurtainLighting($nameHay)) {
            return ['product_family' => 'lighting', 'product_subtype' => null];
        }
        if ($this->matches($nameHay, ['curtain', 'drape', 'blind', 'sheer'])) {
            $sub = $this->firstMatch($nameHay, [
                'curtain' => 'curtain', 'drape' => 'curtain', 'blind' => 'blind', 'sheer' => 'sheer',
            ]) ?? 'curtain';

            return ['product_family' => 'window_treatments', 'product_subtype' => $sub];
        }
        if ($this->matches($nameHay, ['rug ', 'carpet '])) {
            return ['product_family' => 'flooring', 'product_subtype' => 'rug'];
        }
        if ($this->matches($nameHay, ['desk ']) && ! $this->matches($nameHay, ['lamp'])) {
            return ['product_family' => 'furniture', 'product_subtype' => 'desk'];
        }
        if ($this->matches($nameHay, ['wardrobe'])) {
            return ['product_family' => 'furniture', 'product_subtype' => 'wardrobe'];
        }
        // Chairs & stools (after sofa/bed to avoid ambiguity)
        if ($this->matches($nameHay, ['dining chair', 'folding chair', 'armchair', 'stool', 'office chair', 'bar chair', 'rocking chair'])) {
            return ['product_family' => 'furniture', 'product_subtype' => 'chair'];
        }
        if ($this->matches($nameHay, ['chair ']) && ! $this->matches($nameHay, ['lamp', 'light'])) {
            return ['product_family' => 'furniture', 'product_subtype' => 'chair'];
        }
        // Bath mats
        if ($this->matches($nameHay, ['bath mat', 'bathtub mat', 'bath rug', 'shower mat', 'bathroom mat'])) {
            return ['product_family' => 'flooring', 'product_subtype' => 'bath_mat'];
        }
        // Storage furniture
        if ($this->matches($nameHay, ['bookcase', 'shelving unit', 'sideboard', 'hallway unit'])) {
            return ['product_family' => 'furniture', 'product_subtype' => 'storage'];
        }
        if ($this->matches($nameHay, [' chest ', 'drawer chest']) && ! $this->matches($nameHay, ['toy chest'])) {
            return ['product_family' => 'furniture', 'product_subtype' => 'storage'];
        }

        return null;
    }

    /**
     * Alias for classify() used by audit code for readability.
     *
     * @return array{product_family: string|null, product_subtype: string|null, material_tags: array<int, string>, color_tags: array<int, string>}
     */
    public function expectedClassification(string $name, ?string $categoryEn, ?string $category = null): array
    {
        return $this->classify($name, $categoryEn, $category);
    }

    /**
     * @return array{product_family: string|null, product_subtype: string|null, material_tags: array<int, string>, color_tags: array<int, string>}
     */
    public function classify(string $name, ?string $categoryEn, ?string $category = null): array
    {
        $hay = mb_strtolower(trim($name.' '.($categoryEn ?? '').' '.($category ?? '')));

        $family = null;
        $subtype = null;

        // Name-first pass: strong product-name signals win over category
        $nameFirst = $this->classifyFromName($name);
        if ($nameFirst !== null) {
            return [
                'product_family' => $nameFirst['product_family'],
                'product_subtype' => $nameFirst['product_subtype'],
                'material_tags' => $this->extractMaterialTags($hay),
                'color_tags' => $this->extractColorTags($hay),
            ];
        }
        // Homeware / kitchen — exclude from all design pools
        if ($this->matches($hay, [
            'casserole', 'casserol', 'milk pot', 'saucepan', 'frying pan', 'cookware',
            'kitchen pot', 'stock pot', 'tea pot', 'teapot', 'kettle', 'cook pot',
            'кастрюл', 'сотейник', 'казан',
        ]) && ! $this->matches($hay, ['coffee table', 'side table', 'dining table'])) {
            return $this->nullResult($hay);
        }

        if ($this->matches($hay, [' lids', 'lid inhouse', 'ihldss', 'pan lid', 'pot lid'])) {
            return $this->nullResult($hay);
        }

        // Interior doors (Armenian: դuռ / дuռ mixed-script, Russian: дверь, English, domus category)
        if ($this->matches($hay, ['դuռ', 'дuռ', 'belwooddoors', 'interior door', 'doors & windows', 'дверь', 'дверн', 'Միջսենյակային Դռներ'])
            && ! $this->matches($hay, ['door handle', 'door knob', 'outdoor', 'garage'])
        ) {
            $family = 'walls';
            $subtype = 'door';
            // Door handles
        } elseif ($this->matches($hay, ['door handle', 'door knob', 'door fitting', 'дверная ручка', 'ручка двери'])) {
            $family = 'walls';
            $subtype = 'door_handle';
            // Bath / shower mats (before flooring to take priority)
        } elseif ($this->matches($hay, ['bath mat', 'bathtub mat', 'bath rug', 'shower mat', 'bathroom mat'])) {
            $family = 'flooring';
            $subtype = 'bath_mat';
            // Mirrors
        } elseif ($this->matches($hay, ['mirror', 'зеркало', 'հայելի'])
            && ! $this->matches($hay, ['rear view', 'car mirror'])
        ) {
            $family = 'walls';
            $subtype = 'mirror';
            // TV wall mounts (before TV to avoid false overlap)
        } elseif ($this->matches($hay, ['tv wall mount', 'tv mount', 'wall mount', 'audio-video and photo > tv wall'])) {
            $family = 'walls';
            $subtype = 'tv_mount';
            // Televisions
        } elseif ($this->matches($hay, ['audio-video and photo > tv', 'smart tv', 'television', 'qled', 'oled tv', 'the frame'])
            && ! $this->matches($hay, ['tv wall mount', 'tv stand', 'tv_stand', 'tv bench'])
        ) {
            $family = 'furniture';
            $subtype = 'tv';
            // Bathroom sinks / basins
        } elseif ($this->matches($hay, ['bathroom sink', 'wash basin', 'washbasin', 'vessel sink', 'plumbing > bathroom sink'])) {
            $family = 'walls';
            $subtype = 'sink';
        } elseif ($this->matches($hay, ['vase', 'planter', 'flower pot', 'fruit vase', 'գovaz', 'ваза'])) {
            $family = 'furniture';
            $subtype = 'vase';
        } elseif ($this->matches($hay, ['tv stand', 'tv_stand', 'tv unit', 'tv_unit', 'media unit', 'media console', 'tv bench', 'living room furniture > tv'])) {
            $family = 'furniture';
            $subtype = 'tv_stand';
        } elseif ($this->matches($hay, ['plant stand', 'plant_stand', 'flower stand'])) {
            $family = 'furniture';
            $subtype = 'plant_stand';
        } elseif ($this->matches($hay, [
            'artificial plant', 'artificial flower', 'artificial bouquet', 'artificial tree',
            'faux plant', 'faux flower', 'faux tree', 'decorative plant', 'decorative tree',
            'dried flower', 'dried bouquet', 'silk plant', 'silk flower',
            'искусственн', 'штучн',
        ])) {
            $family = 'furniture';
            $subtype = 'decorative_plant';
        } elseif ($this->isBedTextileProduct($hay)) {
            $family = 'home_accessories';
            $subtype = null;
        } elseif ($this->isSeasonalSoftDecor($hay)) {
            $family = 'home_accessories';
            $subtype = null;
        } elseif ($this->matches($hay, [
            'laminate', 'parquet', 'flooring', 'tile', 'porcelain', 'vinyl', 'spc', 'lvt',
            'ковролин', 'ламинат', 'паркет', 'плитк', 'floor panel',
            'Լամինատե Հատակներ', 'Փայտե Հատակներ', 'հատակներ',
            'Սալիկներ', 'Կերամոգրանիտներ', 'Մոզաիկա',
        ])) {
            $family = 'flooring';
            $subtype = $this->firstMatch($hay, [
                'carpet' => 'carpet',
                'rug' => 'rug',
                'laminate' => 'laminate',
                'լամինատե' => 'laminate',
                'parquet' => 'parquet',
                'փայտե' => 'parquet',
                'porcelain' => 'tile',
                'tile' => 'tile',
                'սալիկներ' => 'tile',
                'կերամոգրանիտներ' => 'tile',
                'մոզաիկա' => 'tile',
                'vinyl' => 'vinyl',
            ]) ?? 'laminate';
        } elseif ($this->matches($hay, ['wallpaper', 'wall panel', 'wainscot', 'обои', 'panel 3d', 'wall covering', 'Պաստառ'])) {
            $family = 'walls';
            $subtype = $this->matches($hay, ['wallpaper', 'обои', 'Պաստառ']) ? 'wallpaper' : 'wall_panel';
        } elseif ($this->isDecorativeCurtainLighting($hay)) {
            $family = 'lighting';
            $subtype = null;
        } elseif ($this->matches($hay, ['curtain', 'drape', 'blind', 'sheer', 'штор', 'жалюзи'])) {
            $family = 'window_treatments';
            $subtype = $this->firstMatch($hay, [
                'curtain' => 'curtain',
                'drape' => 'curtain',
                'blind' => 'blind',
                'sheer' => 'sheer',
            ]) ?? 'curtain';
            // Armenian lighting: chandeliers (Ջaheer), table lamps (Сеghani Лusatun), track/spot/shade
        } elseif ($this->matches($hay, ['Ջaheer', 'Ջaheeр', 'Ջահեր'])) {
            $family = 'lighting';
            $subtype = 'ceiling';
        } elseif ($this->matches($hay, ['Սեղանի Լուսատուներ'])) {
            $family = 'lighting';
            $subtype = 'table';
        } elseif ($this->matches($hay, ['Լուսատուներ', 'Լուսամփոփներ', 'Ճաղաշարքային'])) {
            $family = 'lighting';
            $subtype = null;
            // Lighting — 'light' removed intentionally to prevent matching "LIGHT CAPPUCCINO" bed names
        } elseif ($this->matches($hay, [
            'chandelier', 'pendant', 'sconce', 'luminaire', 'lamp', 'люстр', 'светильник', 'spotlight',
            'wall light', 'ceiling light', 'floor light', 'table light', 'track light', 'led light',
        ])) {
            $family = 'lighting';
            $subtype = $this->firstMatch($hay, [
                'chandelier' => 'ceiling',
                'pendant' => 'pendant',
                'ceiling' => 'ceiling',
                'floor lamp' => 'floor',
                'floor light' => 'floor',
                'table lamp' => 'table',
                'table light' => 'table',
                'wall light' => 'wall',
                'sconce' => 'wall',
                'wall' => 'wall',
            ]) ?? 'ceiling';
        } elseif ($this->matches($hay, ['sofa', 'sectional', 'divan', 'диван', 'բազмoց'])) {
            $family = 'furniture';
            $subtype = 'sofa';
        } elseif ($this->matches($hay, ['armchair', 'chair', 'stool', 'стул', 'кресло', 'աթoռ'])) {
            $family = 'furniture';
            $subtype = 'chair';
        } elseif ($this->matches($hay, ['coffee table', 'side table', 'end table', 'console table', 'журнальн'])) {
            $family = 'furniture';
            $subtype = 'coffee_table';
        } elseif ($this->matches($hay, ['dining table', 'dining_table'])) {
            $family = 'furniture';
            $subtype = 'dining_table';
        } elseif ($this->matches($hay, ['desk', 'письменн'])) {
            $family = 'furniture';
            $subtype = 'desk';
        } elseif ($this->matches($hay, ['bed', 'mattress', 'кровать', 'մahczankar', 'Մահճակալ'])
            && ! $this->matches($hay, ['bed linen', 'bedding', 'pillow'])
        ) {
            $family = 'furniture';
            $subtype = 'bed';
        } elseif ($this->matches($hay, ['wardrobe', 'cabinet', 'shelf', 'shelving', 'bookcase', 'storage', 'шкаф', 'комод', 'entrance hall', 'hallway furniture', 'Դարակաշարեր'])) {
            $family = 'furniture';
            $subtype = 'storage';
        } elseif ($this->matches($hay, ['rug', 'carpet', 'ковер', 'gorq', 'carpet '])) {
            $family = 'flooring';
            $subtype = 'rug';
            // Armenian: televisions (Հerusatats'uytner), tables (Сeghanner), general furniture (Кahuyq)
        } elseif ($this->matches($hay, ['Հեռուստացույցներ'])) {
            $family = 'furniture';
            $subtype = 'tv';
        } elseif ($this->matches($hay, ['Սեղաններ'])) {
            $family = 'furniture';
            $subtype = 'dining_table';
        } elseif ($this->matches($hay, ['Ննջասենյակի Կահույք', 'Հյուրասենյակի Կահույք', 'Կահույք'])) {
            $family = 'furniture';
            $subtype = null;
            // Home appliances
        } elseif ($this->matches($hay, [
            'refrigerator', 'fridge', 'washing machine', 'dishwasher', 'tumble dryer',
            'oven', 'built in oven', 'hob', 'hood', 'extractor', 'freezer', 'cooker',
            'microwave', 'major home appliances',
        ])) {
            $family = 'home_appliances';
            $subtype = $this->firstMatch($hay, [
                'refrigerator' => 'refrigerator',
                'fridge' => 'refrigerator',
                'washing' => 'washing_machine',
                'dishwasher' => 'dishwasher',
                'dryer' => 'dryer',
                'oven' => 'oven',
                'hob' => 'hob',
                'hood' => 'hood',
                'freezer' => 'freezer',
                'microwave' => 'microwave',
                'cooker' => 'cooker',
            ]);
            // Catch-all: tableware, soft furnishings, accessories, plumbing, electronics, etc.
        } else {
            $family = 'home_accessories';
            $subtype = null;
        }

        return [
            'product_family' => $family,
            'product_subtype' => $subtype,
            'material_tags' => $this->extractMaterialTags($hay),
            'color_tags' => $this->extractColorTags($hay),
        ];
    }

    /**
     * @return array{product_family: null, product_subtype: null, material_tags: array<int, string>, color_tags: array<int, string>}
     */
    private function nullResult(string $hay): array
    {
        return [
            'product_family' => null,
            'product_subtype' => null,
            'material_tags' => $this->extractMaterialTags($hay),
            'color_tags' => $this->extractColorTags($hay),
        ];
    }

    /**
     * @param  array<string, mixed>  $productPayload
     * @return array<string, mixed>
     */
    public function enrichPayload(array $productPayload): array
    {
        $name = (string) ($productPayload['name_en'] ?? $productPayload['name'] ?? '');
        $categoryEn = isset($productPayload['category_en']) ? (string) $productPayload['category_en'] : null;
        $category = isset($productPayload['category']) ? (string) $productPayload['category'] : null;

        return array_merge($productPayload, $this->classify($name, $categoryEn, $category));
    }

    /**
     * Bed textiles (blankets, plaids) — not flooring or window treatments.
     */
    private function isBedTextileProduct(string $hay): bool
    {
        return $this->matches($hay, [
            'blanket', 'plaid', 'throw', 'duvet', 'comforter', 'bedding', 'bed linen', 'bed sheet',
            'pillow', 'cushion',
        ]);
    }

    /**
     * Seasonal pillows/throws (e.g. XMAS TREE pillow mis-tagged as flooring/tile).
     */
    private function isSeasonalSoftDecor(string $hay): bool
    {
        if (! $this->matches($hay, ['pillow', 'cushion', 'blanket', 'plaid', 'throw'])) {
            return false;
        }

        return $this->matches($hay, ['xmas', 'christmas', 'noel', 'holiday', 'festive']);
    }

    /**
     * LED / fairy / garland "curtain lights" — not fabric window treatments.
     */
    private function isDecorativeCurtainLighting(string $hay): bool
    {
        if (preg_match('/\b(curtain\s*light|christmas\s*light|string\s*light|fairy\s*light|led\s*string|light\s*string|garland)\b/ui', $hay)) {
            return true;
        }

        return str_contains($hay, 'curtain') && preg_match('/\b(led|\d+\s*led)\b/ui', $hay);
    }

    private function matches(string $hay, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($hay, mb_strtolower($n))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function firstMatch(string $hay, array $map): ?string
    {
        foreach ($map as $needle => $subtype) {
            if (str_contains($hay, $needle)) {
                return $subtype;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extractMaterialTags(string $hay): array
    {
        $tags = [];
        foreach (['oak', 'walnut', 'marble', 'linen', 'velvet', 'boucle', 'brass', 'fabric', 'wood', 'metal'] as $m) {
            if (str_contains($hay, $m)) {
                $tags[] = $m;
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * @return array<int, string>
     */
    private function extractColorTags(string $hay): array
    {
        $tags = [];
        foreach ([
            'grey', 'gray', 'white', 'black', 'beige', 'cream', 'oak', 'natural',
            'blue', 'green', 'brown', 'anthracite', 'sand', 'dark', 'light',
            'pink', 'yellow', 'red', 'dusty', 'cappuccino', 'wenge',
        ] as $c) {
            if (str_contains($hay, $c)) {
                $tags[] = $c;
            }
        }

        return array_values(array_unique($tags));
    }
}
