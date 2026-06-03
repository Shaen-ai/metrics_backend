<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class OzonAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $baseUrl = $config['base_url'] ?? 'https://www.ozon.ru';

        // Ozon uses an internal API for search. The mobile API endpoint is more accessible.
        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get("{$baseUrl}/search/", [
                'text' => $query,
                'from_global' => 'true',
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $html = $response->body();

        return $this->parseSearchResults($html, $baseUrl);
    }

    private function parseSearchResults(string $html, string $baseUrl): Collection
    {
        $results = collect();

        // Ozon embeds product data in JSON state within script tags
        if (preg_match('/"items"\s*:\s*(\[.*?\])\s*[,}]/s', $html, $match)) {
            $items = @json_decode($match[1], true);
            if (is_array($items)) {
                foreach (array_slice($items, 0, 20) as $item) {
                    $name = $item['title'] ?? $item['name'] ?? '';
                    $price = (float) preg_replace('/[^\d.]/', '', $item['price'] ?? '0');
                    $link = $item['link'] ?? $item['url'] ?? '';
                    $imageUrl = $item['image'] ?? $item['coverImage'] ?? null;

                    if (! $name || $price <= 0) {
                        continue;
                    }

                    $productUrl = str_starts_with($link, 'http') ? $link : $baseUrl.$link;

                    $results->push(new LiveSearchResult(
                        name: $name,
                        price: $price,
                        currency: 'RUB',
                        productUrl: $productUrl,
                        sourceMarketplace: $this->getMarketplaceName(),
                        sourceKey: 'ozon',
                        imageUrl: $imageUrl,
                        inStock: true,
                        brand: $item['brand'] ?? null,
                        rating: isset($item['rating']) ? (float) $item['rating'] : null,
                        reviewCount: isset($item['reviewCount']) ? (int) $item['reviewCount'] : null,
                    ));
                }
            }
        }

        // Fallback: try regex for price/title/link patterns in HTML or JSON-LD
        if ($results->isEmpty()) {
            preg_match_all('/"\/product\/([^"]+)".*?"title"\s*:\s*"([^"]*)".*?"price"\s*:\s*"?(\d+)/Us', $html, $matches, PREG_SET_ORDER);
            foreach (array_slice($matches, 0, 20) as $m) {
                $slug = $m[1];
                $name = $m[2];
                $price = (float) $m[3];
                if ($price <= 0) {
                    continue;
                }

                $results->push(new LiveSearchResult(
                    name: $name,
                    price: $price,
                    currency: 'RUB',
                    productUrl: "{$baseUrl}/product/{$slug}",
                    sourceMarketplace: $this->getMarketplaceName(),
                    sourceKey: 'ozon',
                    imageUrl: null,
                    inStock: true,
                ));
            }
        }

        return $results;
    }

    public function getMarketplaceName(): string
    {
        return 'Ozon';
    }

    public function getMarketplaceKey(): string
    {
        return 'ozon';
    }
}
