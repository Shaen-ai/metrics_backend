<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class SimalAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $baseUrl = $config['base_url'] ?? 'https://simal.ge';

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

        $crawler->filter('.product-card, .product-item, [data-product-id]')->each(function (Crawler $node) use ($baseUrl, &$results) {
            try {
                $nameEl = $node->filter('a[title], .product-name, h3, h4');
                $name = $nameEl->count() ? trim($nameEl->first()->text('')) : '';

                $linkEl = $node->filter('a[href]');
                $href = $linkEl->count() ? $linkEl->first()->attr('href') : null;

                if (! $name || ! $href) {
                    return;
                }

                $productUrl = str_starts_with($href, 'http') ? $href : $baseUrl.$href;

                $priceEl = $node->filter('.price, .product-price');
                $priceText = $priceEl->count() ? $priceEl->first()->text('') : '0';
                $price = (float) preg_replace('/[^\d.]/', '', $priceText);

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
                    currency: 'GEL',
                    productUrl: $productUrl,
                    sourceMarketplace: $this->getMarketplaceName(),
                    sourceKey: 'simal_ge',
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
        return 'Simal';
    }

    public function getMarketplaceKey(): string
    {
        return 'simal_ge';
    }
}
