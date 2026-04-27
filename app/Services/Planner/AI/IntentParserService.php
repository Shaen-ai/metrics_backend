<?php

namespace App\Services\Planner\AI;

use App\Services\Planner\DTO\PlanRequestDTO;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IntentParserService
{
    private const ALLOWED_ROOM_TYPES = ['bedroom', 'living_room', 'kitchen', 'office', 'children_room', 'unknown'];
    private const ALLOWED_PRODUCT_TYPES = ['wardrobe', 'kitchen', 'storage', 'general'];

    public function parse(PlanRequestDTO $request): array
    {
        $apiKey = env('OPENAI_API_KEY') ?: env('CURSOR_API_KEY');
        if (! $apiKey) {
            throw new RuntimeException('AI API key not configured. Set OPENAI_API_KEY to enable planner intent parsing.');
        }

        $apiUrl = env('AI_API_URL', 'https://api.openai.com/v1/chat/completions');
        $model = env('PLANNER_INTENT_MODEL', env('AI_MODEL', 'gpt-4o-mini'));

        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt(),
            ],
            [
                'role' => 'user',
                'content' => $this->userContent($request),
            ],
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => 700,
        ];

        if (str_contains($apiUrl, 'openai.com')) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout(45)
            ->withToken($apiKey)
            ->acceptJson()
            ->post($apiUrl, $payload);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?: $response->json('message') ?: 'Planner intent request failed.';
            throw new RuntimeException($message);
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Planner intent model returned an empty response.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $content, $matches) !== 1) {
                throw new RuntimeException('Planner intent model did not return JSON.');
            }
            $decoded = json_decode($matches[0], true);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Planner intent JSON could not be decoded.');
        }

        return $this->normalizeIntent($decoded, $request);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You convert a furniture planning request into structured JSON intent only.
Never generate a furniture layout, placement actions, module lists, prices, product IDs, or final plan.

Return JSON with exactly these fields:
- room_type: one of bedroom, living_room, kitchen, office, children_room, unknown
- product_type: one of wardrobe, kitchen, storage, general
- style: string or null
- budget: number or null
- currency: string or null
- dimensions: object with width_m, depth_m, height_m numbers or null
- colors: array of short color/material preferences
- requirements: array of short functional needs
- constraints: array of short constraints or must-not-have notes
- notes: short summary string

Use uploaded room and inspiration images only to improve the intent fields. Do not pretend exact measurements from photos.
PROMPT;
    }

    private function userContent(PlanRequestDTO $request): array|string
    {
        $images = $request->imageContext();
        if ($images === []) {
            return $request->text;
        }

        $parts = [
            [
                'type' => 'text',
                'text' => "Text request:\n".$request->text."\n\nExtract planning intent only.",
            ],
        ];

        foreach ($images as $image) {
            $parts[] = [
                'type' => 'text',
                'text' => $image['kind'] === 'room'
                    ? 'Room photo for context.'
                    : 'Inspiration image for style/material context.',
            ];
            $parts[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:'.$image['mime'].';base64,'.$image['base64'],
                    'detail' => 'low',
                ],
            ];
        }

        return $parts;
    }

    private function normalizeIntent(array $raw, PlanRequestDTO $request): array
    {
        $roomType = $this->enumValue($raw['room_type'] ?? null, self::ALLOWED_ROOM_TYPES, 'unknown');
        $productType = $this->enumValue($raw['product_type'] ?? null, self::ALLOWED_PRODUCT_TYPES, 'general');

        if ($productType === 'general' && $roomType === 'kitchen') {
            $productType = 'kitchen';
        }

        return [
            'room_type' => $roomType,
            'product_type' => $productType,
            'style' => $this->nullableString($raw['style'] ?? null),
            'budget' => $this->nullableFloat($raw['budget'] ?? null),
            'currency' => $this->nullableString($raw['currency'] ?? $request->admin->currency ?? null),
            'dimensions' => $this->normalizeDimensions($raw['dimensions'] ?? []),
            'colors' => $this->stringList($raw['colors'] ?? []),
            'requirements' => $this->stringList($raw['requirements'] ?? []),
            'constraints' => $this->stringList($raw['constraints'] ?? []),
            'notes' => $this->nullableString($raw['notes'] ?? null) ?? '',
        ];
    }

    private function enumValue(mixed $value, array $allowed, string $fallback): string
    {
        $normalized = strtolower(str_replace([' ', '-'], '_', trim((string) $value)));

        return in_array($normalized, $allowed, true) ? $normalized : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private function nullableFloat(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0.0, (float) $value);
    }

    private function normalizeDimensions(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'width_m' => $this->dimension($value['width_m'] ?? null),
            'depth_m' => $this->dimension($value['depth_m'] ?? null),
            'height_m' => $this->dimension($value['height_m'] ?? null),
        ];
    }

    private function dimension(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $n = (float) $value;

        return $n > 0 && $n <= 30 ? round($n, 2) : null;
    }

    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?string {
            $s = $this->nullableString($item);

            return $s === null ? null : $s;
        }, array_slice($value, 0, 12))));
    }
}
