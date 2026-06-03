<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class HoffAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $baseUrl = $config['base_url'] ?? 'https://hoff.ru';

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ])
            ->get("{$baseUrl}/api/v2/search", [
                'q' => $query,
                'limit' => 20,
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $data = $response->json();
        $products = $data['products'] ?? $data['items'] ?? [];

        return collect($products)->take(20)->map(function (array $product) use ($baseUrl) {
            $name = $product['name'] ?? $product['title'] ?? '';
            $price = (float) ($product['price'] ?? 0);
            $oldPrice = (float) ($product['old_price'] ?? $product['original_price'] ?? 0);
            $slug = $product['slug'] ?? $product['url'] ?? '';
            $imageUrl = $product['image'] ?? $product['images'][0] ?? null;

            if ($imageUrl && ! str_starts_with($imageUrl, 'http')) {
                $imageUrl = $baseUrl.$imageUrl;
            }

            $productUrl = str_starts_with($slug, 'http') ? $slug : "{$baseUrl}/{$slug}";

            return new LiveSearchResult(
                name: $name,
                price: $price,
                currency: 'RUB',
                productUrl: $productUrl,
                sourceMarketplace: $this->getMarketplaceName(),
                sourceKey: 'hoff',
                imageUrl: $imageUrl,
                oldPrice: ($oldPrice > $price) ? $oldPrice : null,
                inStock: ($product['in_stock'] ?? true),
                brand: $product['brand'] ?? null,
            );
        })->filter(fn (LiveSearchResult $r) => $r->price > 0 && $r->name !== '');
    }

    public function getMarketplaceName(): string
    {
        return 'Hoff';
    }

    public function getMarketplaceKey(): string
    {
        return 'hoff';
    }
}
