<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class WildberriesAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $searchHost = $config['search_host'] ?? 'search.wb.ru';
        $dest = $config['dest'] ?? -1257786;
        $baseUrl = $config['base_url'] ?? 'https://www.wildberries.ru';
        $limit = $config['limit'] ?? 20;

        $response = Http::timeout(8)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Origin' => $baseUrl,
                'Referer' => $baseUrl.'/',
            ])
            ->get("https://{$searchHost}/exactmatch/ru/common/v7/search", [
                'ab_testing' => 'false',
                'appType' => 1,
                'curr' => $this->getCurrency($config),
                'dest' => $dest,
                'query' => $query,
                'resultset' => 'catalog',
                'sort' => 'popular',
                'spp' => 30,
                'suppressSpellcheck' => 'false',
                'limit' => $limit,
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $data = $response->json();
        $products = $data['data']['products'] ?? [];

        return collect($products)->map(function (array $product) use ($baseUrl, $config) {
            $id = $product['id'] ?? 0;
            $name = $product['name'] ?? '';
            $brand = $product['brand'] ?? null;

            $priceData = $product['sizes'][0]['price'] ?? null;
            $price = $priceData ? ($priceData['product'] ?? $priceData['total'] ?? 0) / 100 : 0;
            $oldPrice = $priceData ? ($priceData['basic'] ?? 0) / 100 : null;

            if ($price <= 0) {
                $price = ($product['priceU'] ?? 0) / 100;
                $oldPrice = ($product['salePriceU'] ?? null) ? ($product['salePriceU'] / 100) : $oldPrice;
            }

            $imageUrl = $this->buildImageUrl($id);
            $productUrl = $baseUrl.'/catalog/'.$id.'/detail.aspx';

            return new LiveSearchResult(
                name: $name,
                price: $price,
                currency: $this->getCurrency($config),
                productUrl: $productUrl,
                sourceMarketplace: $this->getMarketplaceName(),
                sourceKey: $config['key'] ?? 'wildberries',
                imageUrl: $imageUrl,
                oldPrice: ($oldPrice && $oldPrice > $price) ? $oldPrice : null,
                inStock: true,
                brand: $brand,
                rating: $product['reviewRating'] ?? null,
                reviewCount: $product['feedbacks'] ?? null,
            );
        })->filter(fn (LiveSearchResult $r) => $r->price > 0);
    }

    public function getMarketplaceName(): string
    {
        return 'Wildberries';
    }

    public function getMarketplaceKey(): string
    {
        return 'wildberries';
    }

    private function getCurrency(array $config): string
    {
        $locale = $config['locale'] ?? 'ru';

        return match ($locale) {
            'am' => 'AMD',
            'ge' => 'GEL',
            'by' => 'BYN',
            'kz' => 'KZT',
            default => 'RUB',
        };
    }

    private function buildImageUrl(int $productId): string
    {
        $vol = intdiv($productId, 100000);
        $part = intdiv($productId, 1000);

        $host = match (true) {
            $vol <= 143 => 'basket-01',
            $vol <= 287 => 'basket-02',
            $vol <= 431 => 'basket-03',
            $vol <= 719 => 'basket-04',
            $vol <= 1007 => 'basket-05',
            $vol <= 1061 => 'basket-06',
            $vol <= 1115 => 'basket-07',
            $vol <= 1169 => 'basket-08',
            $vol <= 1313 => 'basket-09',
            $vol <= 1601 => 'basket-10',
            $vol <= 1655 => 'basket-11',
            $vol <= 1919 => 'basket-12',
            $vol <= 2045 => 'basket-13',
            $vol <= 2189 => 'basket-14',
            $vol <= 2405 => 'basket-15',
            $vol <= 2621 => 'basket-16',
            $vol <= 2837 => 'basket-17',
            default => 'basket-18',
        };

        return "https://{$host}.wbbasket.ru/vol{$vol}/part{$part}/{$productId}/images/c246x328/1.webp";
    }
}
