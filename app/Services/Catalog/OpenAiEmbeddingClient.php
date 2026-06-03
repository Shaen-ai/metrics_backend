<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiEmbeddingClient
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $vectors = $this->embedBatch([$text]);

        return $vectors[0] ?? [];
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        $apiKey = (string) config('catalog.embedding.openai_api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException(
                'OPENAI_API_KEY is required for catalog embeddings. Set it in backend/.env, then run: php artisan config:clear (or php artisan config:cache after updating .env).'
            );
        }

        $model = (string) config('catalog.embedding.model', 'text-embedding-3-small');
        $dimensions = (int) config('catalog.embedding.dimensions', 1536);

        $payload = [
            'model' => $model,
            'input' => array_values($texts),
        ];
        if ($dimensions > 0) {
            $payload['dimensions'] = $dimensions;
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/embeddings', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI embeddings failed: '.$response->body());
        }

        $rows = $response->json('data', []);
        usort($rows, fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        $out = [];
        foreach ($rows as $row) {
            $embedding = $row['embedding'] ?? [];
            if (! is_array($embedding)) {
                throw new RuntimeException('Invalid embedding response shape.');
            }
            $out[] = array_map('floatval', $embedding);
        }

        return $out;
    }
}
