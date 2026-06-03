<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class DomusSearchAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $baseUrl = $config['base_url'] ?? 'https://domus.am';

        // Domus is Next.js — search is done via their internal API/RSC payload.
        // Try the search page which may return JSON via RSC flight data.
        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/json',
                'RSC' => '1',
                'Next-Router-State-Tree' => '%5B%22%22%5D',
            ])
            ->get("{$baseUrl}/search", [
                'q' => $query,
            ]);

        if (! $response->successful()) {
            return collect();
        }

        $body = $response->body();

        return $this->parseRscPayload($body, $baseUrl);
    }

    private function parseRscPayload(string $body, string $baseUrl): Collection
    {
        $results = collect();

        // Domus RSC payload has product data as JSON fragments within the response.
        // Products typically appear as objects with name, price, slug, images, etc.
        preg_match_all('/"name"\s*:\s*"([^"]+)".*?"price"\s*:\s*(\d+).*?"slug"\s*:\s*"([^"]+)"/Us', $body, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            // Fallback: try to find product JSON objects in flight data
            preg_match_all('/\{[^{}]*"slug"\s*:\s*"([^"]+)"[^{}]*"price"\s*:\s*(\d+)[^{}]*\}/s', $body, $jsonMatches);

            if (! empty($jsonMatches[0])) {
                foreach ($jsonMatches[0] as $jsonStr) {
                    $data = @json_decode($jsonStr, true);
                    if (! $data || empty($data['slug'])) {
                        continue;
                    }

                    $name = $data['name'] ?? $data['title'] ?? $data['slug'];
                    $price = (float) ($data['price'] ?? 0);
                    $slug = $data['slug'];
                    $imageUrl = $data['image'] ?? $data['thumbnail'] ?? $data['mainImage'] ?? null;

                    if ($imageUrl && ! str_starts_with($imageUrl, 'http')) {
                        $imageUrl = $baseUrl.$imageUrl;
                    }

                    $results->push(new LiveSearchResult(
                        name: $name,
                        price: $price,
                        currency: 'AMD',
                        productUrl: "{$baseUrl}/product/{$slug}",
                        sourceMarketplace: $this->getMarketplaceName(),
                        sourceKey: 'domus',
                        imageUrl: $imageUrl,
                        inStock: true,
                    ));
                }
            }

            return $results;
        }

        foreach ($matches as $match) {
            $name = $match[1];
            $price = (float) $match[2];
            $slug = $match[3];

            // Try to extract image URL near this product
            $imageUrl = null;
            $imagePattern = '/'.preg_quote($slug, '/').'.*?"(?:image|thumbnail|mainImage)"\s*:\s*"([^"]+)"/Us';
            if (preg_match($imagePattern, $body, $imgMatch)) {
                $imageUrl = $imgMatch[1];
                if (! str_starts_with($imageUrl, 'http')) {
                    $imageUrl = $baseUrl.$imageUrl;
                }
            }

            $results->push(new LiveSearchResult(
                name: $name,
                price: $price,
                currency: 'AMD',
                productUrl: "{$baseUrl}/product/{$slug}",
                sourceMarketplace: $this->getMarketplaceName(),
                sourceKey: 'domus',
                imageUrl: $imageUrl,
                inStock: true,
            ));
        }

        return $results;
    }

    public function getMarketplaceName(): string
    {
        return 'Domus';
    }

    public function getMarketplaceKey(): string
    {
        return 'domus';
    }
}
