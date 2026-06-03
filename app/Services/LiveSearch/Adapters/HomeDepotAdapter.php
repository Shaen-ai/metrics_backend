<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class HomeDepotAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        // Home Depot has a search API endpoint
        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
                'x-experience-name' => 'general-merchandise',
            ])
            ->get('https://www.homedepot.com/federation-gateway/graphql', [
                'operationName' => 'searchModel',
                'variables' => json_encode([
                    'keyword' => $query,
                    'navParam' => '',
                    'storefilter' => 'ALL',
                    'itemCount' => 20,
                    'startIndex' => 0,
                ]),
            ]);

        if (! $response->successful()) {
            return $this->fallbackHtmlSearch($query, $config);
        }

        $data = $response->json();
        $items = $data['data']['searchModel']['products'] ?? [];

        return collect($items)->take(20)->map(function (array $item) {
            $name = $item['identifiers']['productLabel'] ?? $item['productLabel'] ?? '';
            $price = (float) ($item['pricing']['value'] ?? 0);
            $itemId = $item['identifiers']['itemId'] ?? '';
            $imageUrl = $item['media']['images'][0]['url'] ?? $item['media']['image']['url'] ?? null;
            $productUrl = "https://www.homedepot.com/p/{$itemId}";
            $brand = $item['identifiers']['brandName'] ?? null;
            $rating = $item['reviews']['ratingsReviews']['averageRating'] ?? null;
            $reviewCount = $item['reviews']['ratingsReviews']['totalReviews'] ?? null;

            return new LiveSearchResult(
                name: $name,
                price: $price,
                currency: 'USD',
                productUrl: $productUrl,
                sourceMarketplace: $this->getMarketplaceName(),
                sourceKey: 'home_depot',
                imageUrl: $imageUrl,
                inStock: true,
                brand: $brand,
                rating: $rating ? (float) $rating : null,
                reviewCount: $reviewCount ? (int) $reviewCount : null,
            );
        })->filter(fn (LiveSearchResult $r) => $r->price > 0 && $r->name !== '');
    }

    private function fallbackHtmlSearch(string $query, array $config): Collection
    {
        // Fallback: return empty — Home Depot heavily protects their search
        return collect();
    }

    public function getMarketplaceName(): string
    {
        return 'Home Depot';
    }

    public function getMarketplaceKey(): string
    {
        return 'home_depot';
    }
}
