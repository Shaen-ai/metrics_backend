<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class JyskSearchAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $baseUrl = rtrim($config['base_url'] ?? 'https://jysk.am', '/');

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get("{$baseUrl}/en/search", [
                'q' => $query,
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $crawler = new Crawler($response->body());
        $results = collect();

        $crawler->filter('.product_item')->each(function (Crawler $node) use ($baseUrl, &$results) {
            try {
                $link = $node->filter('h3 a');
                if (! $link->count()) {
                    return;
                }

                $name = trim($link->first()->text(''));
                $href = $link->first()->attr('href');
                if ($name === '' || ! $href) {
                    return;
                }

                $productUrl = str_starts_with($href, 'http')
                    ? $href
                    : $baseUrl.(str_starts_with($href, '/') ? '' : '/').$href;

                $priceText = '';
                $priceNode = $node->filter('.price .current');
                if ($priceNode->count()) {
                    $priceText = $priceNode->first()->text('');
                }
                $price = (float) preg_replace('/\D/', '', $priceText);

                $oldPrice = null;
                $oldNode = $node->filter('.price .old');
                if ($oldNode->count()) {
                    $oldParsed = (float) preg_replace('/\D/', '', $oldNode->first()->text(''));
                    if ($oldParsed > 0 && $oldParsed > $price) {
                        $oldPrice = $oldParsed;
                    }
                }

                $imageUrl = null;
                $img = $node->filter('figure.product_item_img img');
                if ($img->count()) {
                    $src = $img->first()->attr('src') ?? $img->first()->attr('data-src');
                    if ($src) {
                        $imageUrl = str_starts_with($src, 'http') ? $src : $baseUrl.(str_starts_with($src, '/') ? '' : '/').$src;
                    }
                }

                $results->push(new LiveSearchResult(
                    name: $name,
                    price: $price,
                    currency: 'AMD',
                    productUrl: $productUrl,
                    sourceMarketplace: $this->getMarketplaceName(),
                    sourceKey: 'jysk',
                    imageUrl: $imageUrl,
                    oldPrice: $oldPrice,
                    inStock: true,
                ));
            } catch (\Throwable) {
            }
        });

        return $results;
    }

    public function getMarketplaceName(): string
    {
        return 'JYSK';
    }

    public function getMarketplaceKey(): string
    {
        return 'jysk';
    }
}
