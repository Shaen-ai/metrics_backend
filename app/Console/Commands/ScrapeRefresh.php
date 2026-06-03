<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Models\ScrapeUrl;
use App\Services\Scraper\DomusScraper;
use App\Services\Scraper\JyskScraper;
use App\Services\Scraper\VegaScraper;
use Illuminate\Console\Command;

class ScrapeRefresh extends Command
{
    protected $signature = 'scrape:refresh
                            {marketplace : vega, domus or jysk}
                            {--days=7 : Re-queue URLs scraped more than N days ago}';

    protected $description = 'Re-queue already-scraped URLs for a fresh pass (updates prices, catches removed products)';

    public function handle(): int
    {
        $marketplace = $this->argument('marketplace');
        $days = (int) $this->option('days');

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

        $beforePending = ScrapeUrl::marketplace($marketplace)->pending()->count();

        $requeued = $scraper->requeueStaleUrls($days, $this);

        $afterPending = ScrapeUrl::marketplace($marketplace)->pending()->count();
        $totalProducts = ScrapedProduct::marketplace($marketplace)->count();
        $outOfStock = ScrapedProduct::marketplace($marketplace)->where('in_stock', false)->count();

        $this->newLine();
        $this->info("=== {$marketplace} Refresh ===");
        $this->info("  Re-queued:     {$requeued} URLs (scraped > {$days} days ago)");
        $this->info("  Pending now:   {$afterPending} (was {$beforePending})");
        $this->info("  Products:      {$totalProducts} total, {$outOfStock} out of stock");
        $this->newLine();
        $this->info("Run 'scrape:products {$marketplace} --loop' to process the re-queued URLs.");

        return self::SUCCESS;
    }
}
