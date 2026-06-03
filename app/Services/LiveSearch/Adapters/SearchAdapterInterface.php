<?php

namespace App\Services\LiveSearch\Adapters;

use App\Services\LiveSearch\LiveSearchResult;
use Illuminate\Support\Collection;

interface SearchAdapterInterface
{
    /**
     * Execute a live search against the marketplace and return normalized results.
     *
     * @param  string  $query  The user's search term
     * @param  array  $config  Adapter config from marketplaces.php (base_url, locale, etc.)
     * @return Collection<int, LiveSearchResult>
     */
    public function search(string $query, array $config): Collection;

    public function getMarketplaceName(): string;

    public function getMarketplaceKey(): string;
}
