<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class DigikalaAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ])
            ->get('https://api.digikala.com/v2/search/', [
                'q' => $query,
                'page' => 1,
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $data = $response->json();
        $products = $data['data']['products'] ?? [];

        return collect($products)->take(20)->map(function (array $product) {
            $name = $product['title_fa'] ?? $product['title_en'] ?? '';
            $price = (int) ($product['default_variant']['price']['selling_price'] ?? 0);
            $oldPrice = (int) ($product['default_variant']['price']['rrp_price'] ?? 0);

            if ($price <= 0) {
                $price = (int) ($product['price'] ?? 0);
            }

            // Digikala prices are in Toman (display) but stored as Rial
            $priceDisplay = $price / 10;
            $oldPriceDisplay = $oldPrice > 0 ? $oldPrice / 10 : null;

            $imageUrl = $product['images']['main']['url'][0] ?? $product['image'] ?? null;
            $productUrl = "https://www.digikala.com/product/dkp-{$product['id']}";

            $brand = $product['brand']['title_fa'] ?? $product['brand']['title_en'] ?? null;
            $category = $product['category']['title_fa'] ?? null;
            $rating = $product['rating']['rate'] ?? null;
            $reviewCount = $product['rating']['count'] ?? null;

            return new LiveSearchResult(
                name: $name,
                price: (float) $priceDisplay,
                currency: 'IRR',
                productUrl: $productUrl,
                sourceMarketplace: $this->getMarketplaceName(),
                sourceKey: 'digikala',
                imageUrl: $imageUrl,
                oldPrice: ($oldPriceDisplay && $oldPriceDisplay > $priceDisplay) ? (float) $oldPriceDisplay : null,
                inStock: ($product['status'] ?? '') === 'marketable',
                brand: $brand,
                category: $category,
                rating: $rating ? (float) $rating : null,
                reviewCount: $reviewCount ? (int) $reviewCount : null,
            );
        })->filter(fn (LiveSearchResult $r) => $r->price > 0);
    }

    public function getMarketplaceName(): string
    {
        return 'Digikala';
    }

    public function getMarketplaceKey(): string
    {
        return 'digikala';
    }
}
