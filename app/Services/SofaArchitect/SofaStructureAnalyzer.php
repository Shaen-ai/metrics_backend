<?php

namespace App\Services\SofaArchitect;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Vision → structure draft only (form / product kind / section hints). Never invent mm or mechanism SKUs.
 */
class SofaStructureAnalyzer
{
    /** @var list<string> */
    private const PRODUCT_KINDS = [
        'straight_sofa',
        'l_sofa',
        'u_sofa',
        'modular_sofa',
        'armchair',
        'loveseat',
        'chaise',
        'ottoman',
        'bench',
        'daybed',
        'recliner',
    ];

    public function analyze(?UploadedFile $image, string $text = ''): array
    {
        $apiKey = env('OPENAI_API_KEY') ?: env('CURSOR_API_KEY');
        if (! $apiKey) {
            throw new RuntimeException('AI API key not configured. Set OPENAI_API_KEY to enable sofa analyze.');
        }

        $apiUrl = env('AI_API_URL', 'https://api.openai.com/v1/chat/completions');
        $model = env('PLANNER_INTENT_MODEL', env('AI_MODEL', 'gpt-4o-mini'));

        $userContent = [
            [
                'type' => 'text',
                'text' => trim($text) !== ''
                    ? "Workshop notes:\n".trim($text)."\n\nPropose soft furniture structure draft only."
                    : 'Propose soft furniture structure draft from the photo only.',
            ],
        ];

        if ($image) {
            $mime = $image->getMimeType() ?: 'image/jpeg';
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                $mime = 'image/jpeg';
            }
            $b64 = base64_encode(file_get_contents($image->getRealPath()));
            $userContent[] = [
                'type' => 'text',
                'text' => 'Product photo of the sofa or soft seating.',
            ];
            $userContent[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:'.$mime.';base64,'.$b64,
                    'detail' => 'low',
                ],
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $userContent],
            ],
            'temperature' => 0.1,
            'max_tokens' => 600,
        ];

        if (str_contains($apiUrl, 'openai.com')) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout(45)
            ->withToken($apiKey)
            ->acceptJson()
            ->post($apiUrl, $payload);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?: $response->json('message') ?: 'Sofa analyze request failed.';
            throw new RuntimeException($message);
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Sofa analyze model returned an empty response.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $content, $matches) !== 1) {
                throw new RuntimeException('Sofa analyze model did not return JSON.');
            }
            $decoded = json_decode($matches[0], true);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Sofa analyze JSON could not be decoded.');
        }

        return $this->normalize($decoded);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You analyze a sofa or soft seating product photo for workshop structure drafting.
Return JSON only with these fields:
- product_kind: one of straight_sofa, l_sofa, u_sofa, modular_sofa, armchair, loveseat, chaise, ottoman, bench, daybed, recliner
- form: one of straight, L, U (layout hint; must align with product_kind when obvious)
- has_arms: boolean
- has_back: boolean (false for bench / backless seat)
- arm_side: one of left, right, both (for chaise or single-arm layouts)
- channelled_base: boolean (vertical channel / flute look on base or arms)
- section_count: integer seating sections excluding ottoman
- has_ottoman: boolean
- sections: array of { role: main|return|chaise|ottoman, label: string, mechanism_hint: string }
  mechanism_hint is free text only (e.g. "looks like storage" / "recliner" / "unknown") — NEVER invent part numbers or millimetre sizes.
- notes: short string

Do not invent exact measurements. Do not invent mechanism SKUs or hardware.
PROMPT;
    }

    private function normalize(array $raw): array
    {
        $form = $raw['form'] ?? 'straight';
        if (! in_array($form, ['straight', 'L', 'U'], true)) {
            $form = 'straight';
        }

        $productKind = $raw['product_kind'] ?? null;
        if (! is_string($productKind) || ! in_array($productKind, self::PRODUCT_KINDS, true)) {
            $productKind = match ($form) {
                'U' => 'u_sofa',
                'L' => (bool) ($raw['has_ottoman'] ?? false) ? 'l_sofa' : 'l_sofa',
                default => 'straight_sofa',
            };
        }

        $armSide = $raw['arm_side'] ?? 'both';
        if (! in_array($armSide, ['left', 'right', 'both'], true)) {
            $armSide = 'both';
        }

        $sections = [];
        if (is_array($raw['sections'] ?? null)) {
            foreach ($raw['sections'] as $s) {
                if (! is_array($s)) {
                    continue;
                }
                $role = $s['role'] ?? 'main';
                if (! in_array($role, ['main', 'return', 'chaise', 'ottoman'], true)) {
                    $role = 'main';
                }
                $sections[] = [
                    'role' => $role,
                    'label' => is_string($s['label'] ?? null) ? $s['label'] : $role,
                    'mechanism_hint' => is_string($s['mechanism_hint'] ?? null) ? $s['mechanism_hint'] : 'unknown',
                ];
            }
        }

        return [
            'product_kind' => $productKind,
            'form' => $form,
            'has_arms' => (bool) ($raw['has_arms'] ?? true),
            'has_back' => (bool) ($raw['has_back'] ?? true),
            'arm_side' => $armSide,
            'channelled_base' => (bool) ($raw['channelled_base'] ?? false),
            'section_count' => max(1, (int) ($raw['section_count'] ?? count($sections) ?: 1)),
            'has_ottoman' => (bool) ($raw['has_ottoman'] ?? false),
            'sections' => $sections,
            'notes' => is_string($raw['notes'] ?? null) ? $raw['notes'] : '',
        ];
    }
}
