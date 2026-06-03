<?php

namespace App\Console\Commands;

use App\Services\Scraper\DomusScraper;
use App\Services\Scraper\JyskScraper;
use App\Services\Scraper\VegaScraper;
use Illuminate\Console\Command;

class ScrapeDiscoverUrls extends Command
{
    protected $signature = 'scrape:discover
                            {marketplace : vega, domus or jysk}
                            {--categories= : Comma-separated category slugs to discover (default: all)}';

    protected $description = 'Discover product URLs from marketplace sitemaps and store them in scrape_urls';

    public function handle(): int
    {
        $marketplace = $this->argument('marketplace');

        $scraper = match ($marketplace) {
            'vega' => new VegaScraper,
            'domus' => new DomusScraper,
            'jysk' => new JyskScraper,
            default => null,
        };

        if (! $scraper) {
            $this->error("Unknown marketplace: {$marketplace}. Use 'vega', 'domus' or 'jysk'.");

            return self::FAILURE;
        }

        $categoryFilter = null;
        if ($raw = $this->option('categories')) {
            $categoryFilter = array_map('trim', explode(',', $raw));
            $this->info('Filtering to categories: '.implode(', ', $categoryFilter));
        }

        $sweepStartedAt = now();

        $this->info("Discovering URLs for {$marketplace}...");
        $count = $scraper->discoverUrls($this, $categoryFilter);
        $this->info("Done! {$count} new URLs discovered.");

        if ($categoryFilter) {
            $this->info('Skipping listing reconciliation (partial category sweep).');
        } else {
            $this->newLine();
            $this->info('Reconciling listing removals...');
            $deactivated = $scraper->reconcileListingRemovals($sweepStartedAt, $this);
            $this->info("Reconciliation complete: {$deactivated} products marked unavailable.");
        }

        return self::SUCCESS;
    }
}
