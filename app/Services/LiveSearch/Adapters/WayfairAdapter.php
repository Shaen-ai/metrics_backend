<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class WayfairAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $baseUrl = $config['base_url'] ?? 'https://www.wayfair.com';

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get("{$baseUrl}/keyword.php", [
                'keyword' => $query,
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $html = $response->body();
        $results = collect();

        // Try JSON-LD or embedded data first
        if (preg_match_all('/"@type"\s*:\s*"Product".*?"name"\s*:\s*"([^"]+)".*?"price"\s*:\s*"?(\d+\.?\d*)"?.*?"url"\s*:\s*"([^"]+)"/Us', $html, $matches, PREG_SET_ORDER)) {
            foreach (array_slice($matches, 0, 20) as $match) {
                $results->push(new LiveSearchResult(
                    name: $match[1],
                    price: (float) $match[2],
                    currency: str_contains($baseUrl, '.co.uk') ? 'GBP' : 'USD',
                    productUrl: str_starts_with($match[3], 'http') ? $match[3] : $baseUrl.$match[3],
                    sourceMarketplace: $this->getMarketplaceName(),
                    sourceKey: 'wayfair',
                    inStock: true,
                ));
            }
        }

        // Fallback: parse product cards from DOM
        if ($results->isEmpty()) {
            $crawler = new Crawler($html);
            $crawler->filter('[data-hb-id="ProductCard"], .ProductCard')->each(function (Crawler $node) use ($baseUrl, &$results) {
                try {
                    $nameEl = $node->filter('a[data-hb-id="ProductCardLink"], .ProductCard-name');
                    $name = $nameEl->count() ? trim($nameEl->first()->text('')) : '';
                    $href = $nameEl->count() ? $nameEl->first()->attr('href') : null;

                    $priceEl = $node->filter('[data-hb-id="PriceDisplay"], .ProductCard-price');
                    $priceText = $priceEl->count() ? $priceEl->first()->text('') : '0';
                    $price = (float) preg_replace('/[^\d.]/', '', $priceText);

                    if (! $name || $price <= 0) {
                        return;
                    }

                    $productUrl = $href ? (str_starts_with($href, 'http') ? $href : $baseUrl.$href) : $baseUrl;

                    $imgEl = $node->filter('img');
                    $imageUrl = $imgEl->count() ? ($imgEl->first()->attr('src') ?? $imgEl->first()->attr('data-src')) : null;

                    $results->push(new LiveSearchResult(
                        name: $name,
                        price: $price,
                        currency: str_contains($baseUrl, '.co.uk') ? 'GBP' : 'USD',
                        productUrl: $productUrl,
                        sourceMarketplace: $this->getMarketplaceName(),
                        sourceKey: 'wayfair',
                        imageUrl: $imageUrl,
                        inStock: true,
                    ));
                } catch (\Throwable) {
                }
            });
        }

        return $results->take(20);
    }

    public function getMarketplaceName(): string
    {
        return 'Wayfair';
    }

    public function getMarketplaceKey(): string
    {
        return 'wayfair';
    }
}
