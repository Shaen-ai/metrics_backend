<?php

namespace App\Services\Catalog;

use App\Models\CatalogItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Gemini-based AI enrichment for merchant catalog items.
 * Classifies product_family, product_subtype, and generates ai_tags using vision.
 */
class CatalogItemEnricher
{
    private const STYLE_VOCAB = [
        'modern', 'scandinavian', 'industrial', 'bohemian', 'mid-century', 'minimalist',
        'japandi', 'classic', 'rustic', 'coastal', 'art_deco', 'traditional',
    ];

    private const MOOD_VOCAB = ['warm', 'calm', 'cozy', 'bright', 'dramatic', 'serene', 'playful', 'elegant'];

    private const ROOM_VOCAB = [
        'living_room', 'bedroom', 'kitchen', 'dining_room', 'bathroom', 'home_office',
        'hallway', 'children_room', 'studio',
    ];

    private const FAMILIES = [
        'furniture', 'flooring', 'lighting', 'window_treatments', 'walls', 'home_appliances', 'home_accessories',
    ];

    private const SUBTYPES_BY_FAMILY = [
        'furniture' => ['sofa', 'chair', 'coffee_table', 'dining_table', 'table', 'desk', 'bed', 'storage', 'wardrobe', 'tv_stand', 'vase', 'decorative_plant', 'plant_stand', 'tv'],
        'flooring' => ['laminate', 'parquet', 'tile', 'vinyl', 'rug', 'carpet', 'bath_mat'],
        'lighting' => ['ceiling', 'pendant', 'floor', 'table', 'wall'],
        'window_treatments' => ['curtain', 'blind', 'sheer'],
        'walls' => ['wallpaper', 'wall_panel', 'door', 'door_handle', 'mirror', 'tv_mount', 'sink'],
        'home_appliances' => ['refrigerator', 'washing_machine', 'dishwasher', 'dryer', 'oven', 'hob', 'hood', 'freezer', 'microwave', 'cooker'],
        'home_accessories' => [],
    ];

    /**
     * @return array{product_family: string, product_subtype: string|null, material_tags: array, color_tags: array, ai_tags: array}
     */
    public function enrich(CatalogItem $item): array
    {
        $apiKey = $this->apiKey();
        $model = (string) config('catalog.enrichment.model', 'gemini-2.5-flash-lite');
        $parts = $this->buildParts($item);

        $response = Http::timeout(90)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    ['parts' => $parts],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini catalog-item enrich failed: '.$response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Gemini catalog-item enrich returned empty text.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini catalog-item enrich returned invalid JSON.');
        }

        return $this->normalize($decoded);
    }

    private function apiKey(): string
    {
        $key = (string) config('catalog.enrichment.google_api_key', '');
        if ($key === '') {
            throw new RuntimeException('GOOGLE_AI_API_KEY or GEMINI_API_KEY required for catalog enrichment.');
        }

        return $key;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildParts(CatalogItem $item): array
    {
        $parts = [];

        $imageData = $this->resolveImageData($item);
        if ($imageData !== null) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $imageData['mime_type'],
                    'data' => $imageData['base64'],
                ],
            ];
        }

        $parts[] = ['text' => $this->buildPrompt($item)];

        return $parts;
    }

    /**
     * @return array{mime_type: string, base64: string}|null
     */
    private function resolveImageData(CatalogItem $item): ?array
    {
        $image = $item->images()->first();
        if (! $image) {
            return null;
        }

        $url = $image->url;
        if (! $url) {
            return null;
        }

        $relativePath = $url;
        if (str_starts_with($relativePath, '/storage/')) {
            $relativePath = substr($relativePath, strlen('/storage/'));
        }

        if (! Storage::disk('public')->exists($relativePath)) {
            return null;
        }

        $contents = Storage::disk('public')->get($relativePath);
        if (! $contents) {
            return null;
        }

        $mimeType = Storage::disk('public')->mimeType($relativePath) ?: 'image/jpeg';
        $base64 = base64_encode($contents);

        return ['mime_type' => $mimeType, 'base64' => $base64];
    }

    private function buildPrompt(CatalogItem $item): string
    {
        $families = implode(', ', self::FAMILIES);
        $styles = implode(', ', self::STYLE_VOCAB);
        $moods = implode(', ', self::MOOD_VOCAB);
        $rooms = implode(', ', self::ROOM_VOCAB);

        $subtypesDesc = '';
        foreach (self::SUBTYPES_BY_FAMILY as $family => $subtypes) {
            if (empty($subtypes)) {
                $subtypesDesc .= "- {$family}: (return null or a descriptive slug)\n";
            } else {
                $subtypesDesc .= "- {$family}: ".implode(', ', $subtypes)."\n";
            }
        }

        $desc = mb_substr(trim((string) ($item->description ?? '')), 0, 500);
        $category = trim((string) ($item->category ?? ''));

        return <<<PROMPT
You are a home catalog classification and tagging assistant. Analyze the product image and metadata below, then return ONLY valid JSON with these keys:

- product_family: exactly one of [{$families}]
- product_subtype: from the matching family's subtypes below (null if home_accessories or not applicable)
- styles: array from [{$styles}]
- colors: array of common interior color terms (max 6)
- materials: array of common material terms (max 6)
- moods: array from [{$moods}]
- rooms: array from [{$rooms}]

Product subtypes by family:
{$subtypesDesc}
Product info:
name: {$item->name}
category: {$category}
description: {$desc}
dimensions: {$item->width}x{$item->depth}x{$item->height} {$item->dimension_unit}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{product_family: string, product_subtype: string|null, material_tags: array, color_tags: array, ai_tags: array}
     */
    private function normalize(array $raw): array
    {
        $family = $this->normalizeFamily($raw['product_family'] ?? null);
        $subtype = $this->normalizeSubtype($family, $raw['product_subtype'] ?? null);

        $aiTags = [
            'styles' => $this->filterList($raw['styles'] ?? [], self::STYLE_VOCAB),
            'colors' => $this->freeList($raw['colors'] ?? [], 6),
            'materials' => $this->freeList($raw['materials'] ?? [], 6),
            'moods' => $this->filterList($raw['moods'] ?? [], self::MOOD_VOCAB),
            'rooms' => $this->filterList($raw['rooms'] ?? [], self::ROOM_VOCAB),
        ];

        return [
            'product_family' => $family,
            'product_subtype' => $subtype,
            'material_tags' => $aiTags['materials'],
            'color_tags' => $aiTags['colors'],
            'ai_tags' => $aiTags,
        ];
    }

    private function normalizeFamily(mixed $value): string
    {
        $s = mb_strtolower(trim((string) ($value ?? '')));
        if (in_array($s, self::FAMILIES, true)) {
            return $s;
        }

        return 'furniture';
    }

    private function normalizeSubtype(string $family, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = mb_strtolower(trim((string) $value));
        if ($s === '' || $s === 'null') {
            return null;
        }

        $allowed = self::SUBTYPES_BY_FAMILY[$family] ?? [];
        if (empty($allowed)) {
            return $s;
        }

        return in_array($s, $allowed, true) ? $s : null;
    }

    /**
     * @param  array<int, string>  $vocab
     * @return array<int, string>
     */
    private function filterList(mixed $value, array $vocab): array
    {
        if (! is_array($value)) {
            return [];
        }
        $allowed = array_flip($vocab);
        $out = [];
        foreach ($value as $item) {
            $s = mb_strtolower(trim((string) $item));
            if ($s !== '' && isset($allowed[$s])) {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<int, string>
     */
    private function freeList(mixed $value, int $max): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $s = mb_strtolower(trim((string) $item));
            if ($s !== '') {
                $out[] = $s;
            }
            if (count($out) >= $max) {
                break;
            }
        }

        return array_values(array_unique($out));
    }
}
