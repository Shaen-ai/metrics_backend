<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class NoonAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ])
            ->get('https://www.noon.com/_svc/catalog/api/v3/u/search', [
                'q' => $query,
                'limit' => 20,
                'page' => 1,
                'locale' => 'en-ae',
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $data = $response->json();
        $hits = $data['hits'] ?? [];

        return collect($hits)->take(20)->map(function (array $item) {
            $name = $item['name'] ?? $item['title'] ?? '';
            $price = (float) ($item['sale_price'] ?? $item['price'] ?? 0);
            $oldPrice = (float) ($item['price'] ?? 0);
            $sku = $item['sku'] ?? $item['id'] ?? '';
            $imageUrl = $item['image_key'] ?? null;

            if ($imageUrl && ! str_starts_with($imageUrl, 'http')) {
                $imageUrl = "https://f.nooncdn.com/p/{$imageUrl}.jpg";
            }

            $productUrl = "https://www.noon.com/uae-en/{$sku}/p/";
            $brand = $item['brand'] ?? null;

            return new LiveSearchResult(
                name: $name,
                price: $price,
                currency: 'AED',
                productUrl: $productUrl,
                sourceMarketplace: $this->getMarketplaceName(),
                sourceKey: 'noon',
                imageUrl: $imageUrl,
                oldPrice: ($oldPrice > $price) ? $oldPrice : null,
                inStock: true,
                brand: $brand,
            );
        })->filter(fn (LiveSearchResult $r) => $r->price > 0 && $r->name !== '');
    }

    public function getMarketplaceName(): string
    {
        return 'Noon';
    }

    public function getMarketplaceKey(): string
    {
        return 'noon';
    }
}
