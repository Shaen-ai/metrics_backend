<?php

namespace App\Jobs;

use App\Services\Scraper\DomusScraper;
use App\Services\Scraper\JyskScraper;
use App\Services\Scraper\VegaScraper;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class DiscoverSearchUrlsJob
{
    use Dispatchable, Queueable;

    public function __construct(
        public string $query,
        public string $marketplace,
    ) {}

    public function handle(): void
    {
        $maxPages = max(1, (int) config('scraper.search_discovery.max_pages', 3));

        match ($this->marketplace) {
            'vega' => (new VegaScraper)->discoverUrlsFromSearch($this->query, $maxPages),
            'domus' => (new DomusScraper)->discoverUrlsFromSearch($this->query, $maxPages),
            'jysk' => (new JyskScraper)->discoverUrlsFromSearch($this->query, $maxPages),
            default => null,
        };
    }
}
