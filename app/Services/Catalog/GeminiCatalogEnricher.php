<?php

namespace App\Services\Catalog;

use App\Models\ScrapedProduct;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Phase 2: Gemini batch enrichment for structured ai_tags.
 */
class GeminiCatalogEnricher
{
    /** @var array<int, string> */
    private const STYLE_VOCAB = [
        'modern', 'scandinavian', 'industrial', 'bohemian', 'mid-century', 'minimalist',
        'japandi', 'classic', 'rustic', 'coastal', 'art_deco', 'traditional',
    ];

    /** @var array<int, string> */
    private const MOOD_VOCAB = ['warm', 'calm', 'cozy', 'bright', 'dramatic', 'serene', 'playful', 'elegant'];

    /** @var array<int, string> */
    private const ROOM_VOCAB = [
        'living_room', 'bedroom', 'kitchen', 'dining_room', 'bathroom', 'home_office',
        'hallway', 'children_room', 'studio',
    ];

    /**
     * @return array{styles: array<int, string>, colors: array<int, string>, materials: array<int, string>, moods: array<int, string>, rooms: array<int, string>}
     */
    public function enrich(ScrapedProduct $product): array
    {
        $apiKey = $this->apiKey();
        $model = (string) config('catalog.enrichment.model', 'gemini-2.5-flash-lite');
        $prompt = $this->buildPrompt($product);

        $response = Http::timeout(90)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini enrich failed: '.$response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Gemini enrich returned empty text.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini enrich returned invalid JSON.');
        }

        return $this->normalizeTags($decoded);
    }

    private function apiKey(): string
    {
        $key = (string) config('catalog.enrichment.google_api_key', '');
        if ($key === '') {
            throw new RuntimeException('GOOGLE_AI_API_KEY or GEMINI_API_KEY required for catalog enrichment.');
        }

        return $key;
    }

    private function buildPrompt(ScrapedProduct $product): string
    {
        $styles = implode(', ', self::STYLE_VOCAB);
        $moods = implode(', ', self::MOOD_VOCAB);
        $rooms = implode(', ', self::ROOM_VOCAB);

        $desc = mb_substr(trim((string) ($product->description_en ?: $product->description ?: '')), 0, 500);

        return <<<PROMPT
You are a home catalog tagging assistant. Products may be furniture, lighting, flooring, appliances, tableware, or home accessories. Return ONLY valid JSON with keys:
styles, colors, materials, moods, rooms (each an array of lowercase strings).

Pick values ONLY from these controlled lists where applicable:
- styles: {$styles}
- moods: {$moods}
- rooms: {$rooms}
- colors/materials: common interior terms (beige, oak, stainless, ceramic, etc.)
- Leave styles/moods empty if they do not naturally apply to the product type.

Product:
name: {$product->name}
name_en: {$product->name_en}
category: {$product->category_en}
family: {$product->product_family}
subtype: {$product->product_subtype}
description: {$desc}
existing_material_tags: {$this->jsonTags($product->material_tags)}
existing_color_tags: {$this->jsonTags($product->color_tags)}
PROMPT;
    }

    /**
     * @param  array<int, string>|null  $tags
     */
    private function jsonTags(?array $tags): string
    {
        return json_encode($tags ?? [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{styles: array<int, string>, colors: array<int, string>, materials: array<int, string>, moods: array<int, string>, rooms: array<int, string>}
     */
    private function normalizeTags(array $raw): array
    {
        return [
            'styles' => $this->filterList($raw['styles'] ?? [], self::STYLE_VOCAB),
            'colors' => $this->freeList($raw['colors'] ?? [], 6),
            'materials' => $this->freeList($raw['materials'] ?? [], 6),
            'moods' => $this->filterList($raw['moods'] ?? [], self::MOOD_VOCAB),
            'rooms' => $this->filterList($raw['rooms'] ?? [], self::ROOM_VOCAB),
        ];
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
