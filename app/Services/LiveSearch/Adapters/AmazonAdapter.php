<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class AmazonAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $baseUrl = $config['base_url'] ?? 'https://www.amazon.com';

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
            ->get("{$baseUrl}/s", [
                'k' => $query,
                'ref' => 'nb_sb_noss',
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $html = $response->body();
        $crawler = new Crawler($html);
        $results = collect();

        $crawler->filter('[data-component-type="s-search-result"]')->each(function (Crawler $node) use ($baseUrl, &$results) {
            try {
                $nameEl = $node->filter('h2 a span, .a-text-normal');
                $name = $nameEl->count() ? trim($nameEl->first()->text('')) : '';

                $linkEl = $node->filter('h2 a');
                $href = $linkEl->count() ? $linkEl->first()->attr('href') : null;

                if (! $name || ! $href) {
                    return;
                }

                $productUrl = str_starts_with($href, 'http') ? $href : $baseUrl.$href;

                $priceWholeEl = $node->filter('.a-price .a-offscreen, .a-price-whole');
                $priceText = $priceWholeEl->count() ? $priceWholeEl->first()->text('') : '0';
                $price = (float) preg_replace('/[^\d.]/', '', $priceText);

                $imgEl = $node->filter('img.s-image');
                $imageUrl = $imgEl->count() ? $imgEl->first()->attr('src') : null;

                $ratingEl = $node->filter('.a-icon-alt');
                $rating = null;
                if ($ratingEl->count()) {
                    preg_match('/(\d+\.?\d*)/', $ratingEl->first()->text(''), $ratingMatch);
                    $rating = $ratingMatch[1] ?? null;
                }

                if ($price <= 0) {
                    return;
                }

                $results->push(new LiveSearchResult(
                    name: $name,
                    price: $price,
                    currency: $this->getCurrency($baseUrl),
                    productUrl: $productUrl,
                    sourceMarketplace: $this->getMarketplaceName(),
                    sourceKey: 'amazon',
                    imageUrl: $imageUrl,
                    inStock: true,
                    rating: $rating ? (float) $rating : null,
                ));
            } catch (\Throwable) {
            }
        });

        return $results->take(20);
    }

    private function getCurrency(string $baseUrl): string
    {
        return match (true) {
            str_contains($baseUrl, '.ae') => 'AED',
            str_contains($baseUrl, '.tr') => 'TRY',
            str_contains($baseUrl, '.co.uk') => 'GBP',
            str_contains($baseUrl, '.de'), str_contains($baseUrl, '.fr'), str_contains($baseUrl, '.it'), str_contains($baseUrl, '.es') => 'EUR',
            default => 'USD',
        };
    }

    public function getMarketplaceName(): string
    {
        return 'Amazon';
    }

    public function getMarketplaceKey(): string
    {
        return 'amazon';
    }
}
