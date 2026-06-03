<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class KoctasAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $baseUrl = $config['base_url'] ?? 'https://www.koctas.com.tr';

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get("{$baseUrl}/search", [
                'q' => $query,
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $html = $response->body();
        $crawler = new Crawler($html);
        $results = collect();

        $crawler->filter('[data-product-id], .product-card, .product-item')->each(function (Crawler $node) use ($baseUrl, &$results) {
            try {
                $nameEl = $node->filter('a[title], .product-name, .product-title, h3, h4');
                $name = $nameEl->count() ? trim($nameEl->first()->text('')) : '';

                $linkEl = $node->filter('a[href*="/p/"], a[href*="/product"]');
                $href = $linkEl->count() ? $linkEl->first()->attr('href') : null;

                if (! $name || ! $href) {
                    return;
                }

                $productUrl = str_starts_with($href, 'http') ? $href : $baseUrl.$href;

                $priceEl = $node->filter('[data-price], .price, .product-price');
                $priceText = $priceEl->count() ? $priceEl->first()->text('') : '0';
                $price = (float) preg_replace('/[^\d,.]/', '', str_replace(',', '.', $priceText));

                $imgEl = $node->filter('img');
                $imageUrl = null;
                if ($imgEl->count()) {
                    $src = $imgEl->first()->attr('data-src') ?? $imgEl->first()->attr('src');
                    if ($src) {
                        $imageUrl = str_starts_with($src, 'http') ? $src : $baseUrl.$src;
                    }
                }

                if ($price <= 0) {
                    return;
                }

                $results->push(new LiveSearchResult(
                    name: $name,
                    price: $price,
                    currency: 'TRY',
                    productUrl: $productUrl,
                    sourceMarketplace: $this->getMarketplaceName(),
                    sourceKey: 'koctas',
                    imageUrl: $imageUrl,
                    inStock: true,
                ));
            } catch (\Throwable) {
            }
        });

        return $results->take(20);
    }

    public function getMarketplaceName(): string
    {
        return 'Koçtaş';
    }

    public function getMarketplaceKey(): string
    {
        return 'koctas';
    }
}
