<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class IkeaAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $countryCode = $config['country_code'] ?? 'us';
        $languageCode = $config['language_code'] ?? 'en';

        // IKEA's search API (publicly accessible)
        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ])
            ->get("https://sik.search.blue.cdtapps.com/{$countryCode}/{$languageCode}/search-result-page", [
                'q' => $query,
                'size' => 20,
                'types' => 'PRODUCT',
                'subcategories-style' => 'tree-navigation',
                'sort' => 'RELEVANCE',
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $data = $response->json();
        $items = $data['searchResultPage']['products']['main']['items'] ?? [];

        return collect($items)->take(20)->map(function (array $item) use ($config) {
            $product = $item['product'] ?? $item;
            $name = $product['name'] ?? '';
            $typeName = $product['typeName'] ?? '';
            $fullName = $typeName ? "{$name} {$typeName}" : $name;

            $priceData = $product['salesPrice'] ?? $product['price'] ?? null;
            $price = 0;
            $currency = 'USD';

            if ($priceData) {
                $price = (float) ($priceData['numeral'] ?? 0);
                $currency = $priceData['currencyCode'] ?? 'USD';
            }

            $imageUrl = $product['mainImageUrl'] ?? null;
            $productUrl = $product['pipUrl'] ?? ($config['base_url'] ?? 'https://www.ikea.com').'/p/'.$product['id'];

            $rating = $product['ratingValue'] ?? null;
            $reviewCount = $product['ratingCount'] ?? null;

            return new LiveSearchResult(
                name: $fullName,
                price: $price,
                currency: $currency,
                productUrl: $productUrl,
                sourceMarketplace: $this->getMarketplaceName(),
                sourceKey: $config['key'] ?? 'ikea',
                imageUrl: $imageUrl,
                inStock: true,
                brand: 'IKEA',
                rating: $rating ? (float) $rating : null,
                reviewCount: $reviewCount ? (int) $reviewCount : null,
            );
        })->filter(fn (LiveSearchResult $r) => $r->price > 0);
    }

    public function getMarketplaceName(): string
    {
        return 'IKEA';
    }

    public function getMarketplaceKey(): string
    {
        return 'ikea';
    }
}
