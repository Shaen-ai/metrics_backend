<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class AliexpressAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        // AliExpress exposes product search via their affiliate/mobile API
        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json',
                'Referer' => 'https://www.aliexpress.com/',
            ])
            ->get('https://www.aliexpress.com/fn/search-pc/index', [
                'SearchText' => $query,
                'page' => 1,
                'limit' => 20,
                'trafficChannel' => 'main',
                'catId' => 0,
                'CatId' => 0,
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $data = $response->json();
        $items = $data['data']['root']['fields']['mods']['itemList']['content'] ?? $data['items'] ?? [];

        return collect($items)->take(20)->map(function (array $item) {
            $name = $item['title']['displayTitle'] ?? $item['title'] ?? $item['name'] ?? '';
            $priceData = $item['prices'] ?? $item['price'] ?? null;

            $price = 0;
            if (is_array($priceData)) {
                $price = (float) ($priceData['salePrice']['minPrice'] ?? $priceData['originalPrice']['minPrice'] ?? 0);
            } elseif (is_string($priceData) || is_numeric($priceData)) {
                $price = (float) preg_replace('/[^\d.]/', '', (string) $priceData);
            }

            $imageUrl = $item['image']['imgUrl'] ?? $item['imageUrl'] ?? $item['image'] ?? null;
            if ($imageUrl && ! str_starts_with($imageUrl, 'http')) {
                $imageUrl = 'https:'.$imageUrl;
            }

            $productUrl = $item['productDetailUrl'] ?? $item['url'] ?? '';
            if ($productUrl && ! str_starts_with($productUrl, 'http')) {
                $productUrl = 'https://www.aliexpress.com'.$productUrl;
            }

            $rating = $item['evaluation'] ?? $item['starRating'] ?? null;
            $orders = $item['trade']['tradeDesc'] ?? null;

            return new LiveSearchResult(
                name: $name,
                price: $price,
                currency: 'USD',
                productUrl: $productUrl,
                sourceMarketplace: $this->getMarketplaceName(),
                sourceKey: 'aliexpress',
                imageUrl: $imageUrl,
                inStock: true,
                rating: $rating ? (float) $rating : null,
            );
        })->filter(fn (LiveSearchResult $r) => $r->price > 0 && $r->name !== '');
    }

    public function getMarketplaceName(): string
    {
        return 'AliExpress';
    }

    public function getMarketplaceKey(): string
    {
        return 'aliexpress';
    }
}
