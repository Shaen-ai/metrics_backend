<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class HomeCentreAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $baseUrl = $config['base_url'] ?? 'https://www.homecentre.com';

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ])
            ->get("{$baseUrl}/api/search", [
                'q' => $query,
                'page' => 1,
                'pageSize' => 20,
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $data = $response->json();
        $products = $data['products'] ?? $data['items'] ?? $data['results'] ?? [];

        return collect($products)->take(20)->map(function (array $product) use ($baseUrl) {
            $name = $product['name'] ?? $product['title'] ?? '';
            $price = (float) ($product['price'] ?? $product['salePrice'] ?? 0);
            $imageUrl = $product['image'] ?? $product['imageUrl'] ?? null;
            $slug = $product['slug'] ?? $product['url'] ?? '';
            $productUrl = str_starts_with($slug, 'http') ? $slug : "{$baseUrl}/{$slug}";

            return new LiveSearchResult(
                name: $name,
                price: $price,
                currency: 'AED',
                productUrl: $productUrl,
                sourceMarketplace: $this->getMarketplaceName(),
                sourceKey: 'home_centre',
                imageUrl: $imageUrl,
                inStock: true,
                brand: $product['brand'] ?? null,
            );
        })->filter(fn (LiveSearchResult $r) => $r->price > 0 && $r->name !== '');
    }

    public function getMarketplaceName(): string
    {
        return 'Home Centre';
    }

    public function getMarketplaceKey(): string
    {
        return 'home_centre';
    }
}
