<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class TrendyolAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json',
                'Origin' => 'https://www.trendyol.com',
                'Referer' => 'https://www.trendyol.com/',
            ])
            ->get('https://public.trendyol.com/discovery-web-searchgw-service/v2/api/infinite-scroll/sr', [
                'q' => $query,
                'qt' => $query,
                'st' => $query,
                'os' => 1,
                'pi' => 1,
                'culture' => 'tr-TR',
                'userGenderId' => 0,
                'pId' => 0,
                'scoringAlgorithmId' => 2,
                'categoryRelevancyEnabled' => 'false',
                'isLegalRequirementConfirmed' => 'false',
                'searchStrategyType' => 'DEFAULT',
                'productStampType' => 'TypeA',
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $data = $response->json();
        $products = $data['result']['products'] ?? [];

        return collect($products)->take(20)->map(function (array $product) {
            $id = $product['id'] ?? 0;
            $name = $product['name'] ?? '';
            $brand = $product['brand']['name'] ?? null;

            $price = $product['price']['sellingPrice'] ?? $product['price']['originalPrice'] ?? 0;
            $oldPrice = $product['price']['originalPrice'] ?? null;

            $imageUrl = null;
            if (! empty($product['images'])) {
                $firstImage = is_array($product['images']) ? $product['images'][0] : $product['images'];
                $imageUrl = "https://cdn.dsmcdn.com/ty{$firstImage}";
            } elseif (! empty($product['image'])) {
                $imageUrl = "https://cdn.dsmcdn.com/ty{$product['image']}";
            }

            $url = $product['url'] ?? "/p-{$id}";
            $productUrl = str_starts_with($url, 'http') ? $url : "https://www.trendyol.com{$url}";

            $category = $product['categoryName'] ?? null;
            $rating = $product['ratingScore']['averageRating'] ?? null;
            $reviewCount = $product['ratingScore']['totalRatingCount'] ?? null;

            return new LiveSearchResult(
                name: $name,
                price: (float) $price,
                currency: 'TRY',
                productUrl: $productUrl,
                sourceMarketplace: $this->getMarketplaceName(),
                sourceKey: 'trendyol',
                imageUrl: $imageUrl,
                oldPrice: ($oldPrice && (float) $oldPrice > (float) $price) ? (float) $oldPrice : null,
                inStock: true,
                brand: $brand,
                category: $category,
                rating: $rating ? (float) $rating : null,
                reviewCount: $reviewCount ? (int) $reviewCount : null,
            );
        })->filter(fn (LiveSearchResult $r) => $r->price > 0);
    }

    public function getMarketplaceName(): string
    {
        return 'Trendyol';
    }

    public function getMarketplaceKey(): string
    {
        return 'trendyol';
    }
}
