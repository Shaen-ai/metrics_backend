<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class LeroyMerlinAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $baseUrl = $config['base_url'] ?? 'https://leroymerlin.ru';

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ])
            ->get("{$baseUrl}/search/", [
                'q' => $query,
                'suggest' => 'true',
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $body = $response->body();
        $results = collect();

        // Try JSON API response
        $data = @json_decode($body, true);
        if (is_array($data) && isset($data['products'])) {
            foreach (array_slice($data['products'], 0, 20) as $product) {
                $name = $product['name'] ?? $product['title'] ?? '';
                $price = (float) ($product['price']['main_price'] ?? $product['price'] ?? 0);
                $slug = $product['slug'] ?? $product['id'] ?? '';
                $imageUrl = $product['image'] ?? $product['images'][0] ?? null;

                if (! $name || $price <= 0) {
                    continue;
                }

                $results->push(new LiveSearchResult(
                    name: $name,
                    price: $price,
                    currency: str_contains($baseUrl, '.fr') ? 'EUR' : 'RUB',
                    productUrl: "{$baseUrl}/product/{$slug}",
                    sourceMarketplace: $this->getMarketplaceName(),
                    sourceKey: 'leroy_merlin',
                    imageUrl: $imageUrl,
                    inStock: true,
                    brand: $product['brand'] ?? null,
                ));
            }
        }

        return $results;
    }

    public function getMarketplaceName(): string
    {
        return 'Leroy Merlin';
    }

    public function getMarketplaceKey(): string
    {
        return 'leroy_merlin';
    }
}
