<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use App\Support\VegaImageUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class VegaSearchAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $baseUrl = $config['base_url'] ?? 'https://vega.am';

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get("{$baseUrl}/am/search-am/", [
                'search' => $query,
                'limit' => 20,
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $html = $response->body();
        $crawler = new Crawler($html);

        $results = collect();

        $crawler->filter('.product-layout, .product-thumb')->each(function (Crawler $node) use ($baseUrl, &$results) {
            try {
                $nameEl = $node->filter('.caption a, .product-name a, h4 a');
                $name = $nameEl->count() ? trim($nameEl->first()->text('')) : '';
                $href = $nameEl->count() ? $nameEl->first()->attr('href') : null;

                if (! $name || ! $href) {
                    return;
                }

                $productUrl = str_starts_with($href, 'http') ? $href : $baseUrl.$href;

                $priceEl = $node->filter('.price-new, .price');
                $priceText = $priceEl->count() ? $priceEl->first()->text('') : '0';
                $price = (float) preg_replace('/[^\d.]/', '', $priceText);

                $oldPriceEl = $node->filter('.price-old');
                $oldPrice = null;
                if ($oldPriceEl->count()) {
                    $oldPrice = (float) preg_replace('/[^\d.]/', '', $oldPriceEl->first()->text(''));
                }

                $imgEl = $node->filter('img');
                $imageUrl = null;
                if ($imgEl->count()) {
                    $src = $imgEl->first()->attr('data-src') ?? $imgEl->first()->attr('src');
                    if ($src) {
                        $imageUrl = str_starts_with($src, 'http') ? $src : $baseUrl.$src;
                        $imageUrl = VegaImageUrl::normalize($imageUrl) ?? $imageUrl;
                    }
                }

                $results->push(new LiveSearchResult(
                    name: $name,
                    price: $price,
                    currency: 'AMD',
                    productUrl: $productUrl,
                    sourceMarketplace: $this->getMarketplaceName(),
                    sourceKey: 'vega',
                    imageUrl: $imageUrl,
                    oldPrice: ($oldPrice && $oldPrice > $price) ? $oldPrice : null,
                    inStock: true,
                ));
            } catch (\Throwable) {
                // Skip malformed product entries
            }
        });

        return $results;
    }

    public function getMarketplaceName(): string
    {
        return 'Vega';
    }

    public function getMarketplaceKey(): string
    {
        return 'vega';
    }
}
