<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CatalogItemQdrantClient
{
    public function ensureCollection(): void
    {
        $name = $this->collectionName();
        $size = (int) config('catalog.embedding.dimensions', 1536);

        $check = $this->request('GET', "/collections/{$name}");
        if ($check->successful()) {
            return;
        }

        $create = $this->request('PUT', "/collections/{$name}", [
            'vectors' => [
                'size' => $size,
                'distance' => 'Cosine',
            ],
        ]);

        if (! $create->successful()) {
            throw new RuntimeException('Qdrant create collection failed: '.$create->body());
        }

        $fieldSchemas = [
            'admin_id' => 'keyword',
            'product_family' => 'keyword',
            'product_subtype' => 'keyword',
            'in_stock' => 'bool',
        ];

        foreach ($fieldSchemas as $field => $schema) {
            $this->request('PUT', "/collections/{$name}/index", [
                'field_name' => $field,
                'field_schema' => $schema,
            ]);
        }
    }

    /**
     * @param  array<int, array{id: string, vector: array<int, float>, payload: array<string, mixed>}>  $points
     */
    public function upsertPoints(array $points): void
    {
        if ($points === []) {
            return;
        }

        $name = $this->collectionName();
        $response = $this->request('PUT', "/collections/{$name}/points", [
            'points' => array_map(fn ($p) => [
                'id' => $p['id'],
                'vector' => $p['vector'],
                'payload' => $p['payload'],
            ], $points),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Qdrant upsert failed: '.$response->body());
        }
    }

    /**
     * @param  array<int, float>  $vector
     * @param  array<string, mixed>  $filterMust  Additional filter conditions beyond the mandatory admin_id filter
     * @return array<int, array{product_id: string, score: float, payload: array<string, mixed>}>
     */
    public function search(
        array $vector,
        string $adminId,
        int $limit,
        array $filterMust = [],
    ): array {
        $name = $this->collectionName();

        $mustClauses = [
            ['key' => 'admin_id', 'match' => ['value' => $adminId]],
            ...array_values($filterMust),
        ];

        $body = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
            'filter' => [
                'must' => $mustClauses,
            ],
        ];

        $response = $this->request('POST', "/collections/{$name}/points/search", $body);
        if (! $response->successful()) {
            throw new RuntimeException('Qdrant search failed: '.$response->body());
        }

        $hits = [];
        foreach ($response->json('result', []) as $row) {
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
            $productId = (string) ($payload['product_id'] ?? $row['id'] ?? '');
            if ($productId === '') {
                continue;
            }
            $hits[] = [
                'product_id' => $productId,
                'score' => (float) ($row['score'] ?? 0),
                'payload' => $payload,
            ];
        }

        return $hits;
    }

    public function deleteProduct(string $productId): void
    {
        $name = $this->collectionName();
        $this->request('POST', "/collections/{$name}/points/delete", [
            'points' => [$productId],
        ]);
    }

    /**
     * @param  array<string>  $productIds
     */
    public function deleteProducts(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $name = $this->collectionName();
        $this->request('POST', "/collections/{$name}/points/delete", [
            'points' => array_values($productIds),
        ]);
    }

    /**
     * Update payload fields on existing points without re-embedding.
     *
     * @param  array<string>  $pointIds
     * @param  array<string, mixed>  $payload
     */
    public function setPayload(array $pointIds, array $payload): void
    {
        if ($pointIds === [] || $payload === []) {
            return;
        }

        $name = $this->collectionName();
        $response = $this->request('POST', "/collections/{$name}/points/payload", [
            'payload' => $payload,
            'points' => array_values($pointIds),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Qdrant set payload failed: '.$response->body());
        }
    }

    /**
     * Scroll through all point IDs in the collection.
     *
     * @return array<string>
     */
    public function scrollAllIds(): array
    {
        $name = $this->collectionName();
        $ids = [];
        $offset = null;

        do {
            $body = [
                'limit' => 1000,
                'with_payload' => false,
                'with_vector' => false,
            ];
            if ($offset !== null) {
                $body['offset'] = $offset;
            }

            $response = $this->request('POST', "/collections/{$name}/points/scroll", $body);
            if (! $response->successful()) {
                throw new RuntimeException('Qdrant scroll failed: '.$response->body());
            }

            $result = $response->json('result', []);
            $points = $result['points'] ?? [];
            foreach ($points as $point) {
                $ids[] = (string) $point['id'];
            }

            $offset = $result['next_page_offset'] ?? null;
        } while ($offset !== null);

        return $ids;
    }

    private function collectionName(): string
    {
        return (string) config('catalog.catalog_items_qdrant.collection', 'catalog_items_v1');
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function request(string $method, string $path, ?array $json = null)
    {
        $url = config('catalog.qdrant.url').$path;
        $pending = Http::timeout(60)->acceptJson();
        $apiKey = config('catalog.qdrant.api_key');
        if ($apiKey) {
            $pending = $pending->withHeaders(['api-key' => $apiKey]);
        }

        return $json === null
            ? $pending->send($method, $url)
            : $pending->send($method, $url, ['json' => $json]);
    }
}
