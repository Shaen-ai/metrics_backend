<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class QdrantCatalogClient
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
            'product_family' => 'keyword',
            'product_subtype' => 'keyword',
            'in_stock' => 'bool',
            'source_marketplace' => 'keyword',
            'priority' => 'integer',
        ];

        foreach ($fieldSchemas as $field => $schema) {
            $this->request('PUT', "/collections/{$name}/index", [
                'field_name' => $field,
                'field_schema' => $schema,
            ]);
        }
    }

    /**
     * @param  array<int, array{id: int, vector: array<int, float>, payload: array<string, mixed>}>  $points
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
     * @param  array<string, mixed>  $filterMust
     * @return array<int, array{product_id: int, score: float, payload: array<string, mixed>}>
     */
    public function search(
        array $vector,
        int $limit,
        array $filterMust = [],
    ): array {
        $name = $this->collectionName();
        $body = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
        ];

        if ($filterMust !== []) {
            $body['filter'] = [
                'must' => array_values($filterMust),
            ];
        }

        $response = $this->request('POST', "/collections/{$name}/points/search", $body);
        if (! $response->successful()) {
            throw new RuntimeException('Qdrant search failed: '.$response->body());
        }

        $hits = [];
        foreach ($response->json('result', []) as $row) {
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
            $productId = (int) ($payload['product_id'] ?? $row['id'] ?? 0);
            if ($productId <= 0) {
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

    /**
     * @param  array<int, array{vector: array<int, float>, limit: int, filterMust?: array<int, array<string, mixed>>}>  $searches
     * @return array<int, array<int, array{product_id: int, score: float, payload: array<string, mixed>}>>
     */
    public function searchBatch(array $searches): array
    {
        if ($searches === []) {
            return [];
        }

        $name = $this->collectionName();
        $payloadSearches = [];

        foreach ($searches as $search) {
            $body = [
                'vector' => $search['vector'],
                'limit' => $search['limit'],
                'with_payload' => true,
            ];

            $filterMust = $search['filterMust'] ?? [];
            if ($filterMust !== []) {
                $body['filter'] = [
                    'must' => array_values($filterMust),
                ];
            }

            $payloadSearches[] = $body;
        }

        $response = $this->request('POST', "/collections/{$name}/points/search/batch", [
            'searches' => $payloadSearches,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Qdrant batch search failed: '.$response->body());
        }

        $results = [];
        foreach ($response->json('result', []) as $batchResult) {
            $hits = [];
            foreach ($batchResult as $row) {
                $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
                $productId = (int) ($payload['product_id'] ?? $row['id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $hits[] = [
                    'product_id' => $productId,
                    'score' => (float) ($row['score'] ?? 0),
                    'payload' => $payload,
                ];
            }
            $results[] = $hits;
        }

        return $results;
    }

    public function deleteProduct(int $productId): void
    {
        $name = $this->collectionName();
        $this->request('POST', "/collections/{$name}/points/delete", [
            'points' => [$productId],
        ]);
    }

    /**
     * @param  array<int>  $productIds
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
     * @param  array<int>  $productIds
     * @param  array<string, mixed>  $payload
     */
    public function setPayload(array $productIds, array $payload): void
    {
        if ($productIds === [] || $payload === []) {
            return;
        }

        $name = $this->collectionName();
        $response = $this->request('POST', "/collections/{$name}/points/payload", [
            'payload' => $payload,
            'points' => array_values($productIds),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Qdrant set payload failed: '.$response->body());
        }
    }

    /**
     * Scroll through all point IDs in the collection.
     *
     * @return array<int>
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
                $ids[] = (int) $point['id'];
            }

            $offset = $result['next_page_offset'] ?? null;
        } while ($offset !== null);

        return $ids;
    }

    public function ensureMultimodalCollection(): void
    {
        $name = $this->multimodalCollectionName();
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
            throw new RuntimeException('Qdrant create multimodal collection failed: '.$create->body());
        }

        foreach (['product_family', 'product_subtype'] as $field) {
            $this->request('PUT', "/collections/{$name}/index", [
                'field_name' => $field,
                'field_schema' => 'keyword',
            ]);
        }
    }

    /**
     * @param  array<int, array{id: int, vector: array<int, float>, payload: array<string, mixed>}>  $points
     */
    public function upsertMultimodalPoints(array $points): void
    {
        if ($points === []) {
            return;
        }

        $name = $this->multimodalCollectionName();
        $response = $this->request('PUT', "/collections/{$name}/points", [
            'points' => array_map(fn ($p) => [
                'id' => $p['id'],
                'vector' => $p['vector'],
                'payload' => $p['payload'],
            ], $points),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Qdrant multimodal upsert failed: '.$response->body());
        }
    }

    private function collectionName(): string
    {
        return (string) config('catalog.qdrant.collection', 'catalog_products_v1');
    }

    private function multimodalCollectionName(): string
    {
        return (string) config('catalog.qdrant.multimodal_collection', 'catalog_products_image_v1');
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
