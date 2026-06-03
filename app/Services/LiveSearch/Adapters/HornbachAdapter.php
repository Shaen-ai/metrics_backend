<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class HornbachAdapter implements SearchAdapterInterface
{
    public function search(string $query, array $config): Collection
    {
        $baseUrl = $config['base_url'] ?? 'https://www.hornbach.de';

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ])
            ->get("{$baseUrl}/s/{$query}/");

        if (! $response->successful()) {
            return collect();
        }

        $body = $response->body();
        $results = collect();

        // Try embedded JSON product data
        if (preg_match_all('/"@type"\s*:\s*"Product".*?"name"\s*:\s*"([^"]+)".*?"price"\s*:\s*"?(\d+\.?\d*)"?.*?"url"\s*:\s*"([^"]+)"/Us', $body, $matches, PREG_SET_ORDER)) {
            foreach (array_slice($matches, 0, 20) as $match) {
                $results->push(new LiveSearchResult(
                    name: html_entity_decode($match[1]),
                    price: (float) $match[2],
                    currency: 'EUR',
                    productUrl: str_starts_with($match[3], 'http') ? $match[3] : $baseUrl.$match[3],
                    sourceMarketplace: $this->getMarketplaceName(),
                    sourceKey: 'hornbach',
                    inStock: true,
                ));
            }
        }

        return $results;
    }

    public function getMarketplaceName(): string
    {
        return 'Hornbach';
    }

    public function getMarketplaceKey(): string
    {
        return 'hornbach';
    }
}
