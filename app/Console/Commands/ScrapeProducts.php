<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Models\ScrapeUrl;
use App\Services\Scraper\DomusScraper;
use App\Services\Scraper\JyskScraper;
use App\Services\Scraper\VegaScraper;
use Illuminate\Console\Command;

class ScrapeProducts extends Command
{
    protected $signature = 'scrape:products
                            {marketplace : vega, domus or jysk}
                            {--batch=1 : Number of products to scrape per batch}
                            {--loop : Run continuously until all pending URLs are scraped}';

    protected $description = 'Scrape product pages from a marketplace (respecting 10s rate limit per request)';

    public function handle(): int
    {
        $marketplace = $this->argument('marketplace');
        $batchSize = (int) $this->option('batch');
        $loop = $this->option('loop');

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

        $totalPending = ScrapeUrl::marketplace($marketplace)->pending()->count();
        $totalDone = ScrapeUrl::marketplace($marketplace)->where('status', 'done')->count();
        $totalFailed = ScrapeUrl::marketplace($marketplace)->where('status', 'failed')->count();
        $totalProducts = ScrapedProduct::marketplace($marketplace)->count();

        $this->info("=== {$marketplace} Scraper ===");
        $this->info("  URLs:     {$totalDone} done / {$totalFailed} failed / {$totalPending} pending");
        $this->info("  Products: {$totalProducts} in DB");
        $this->info("  Rate:     10s per request, batch size {$batchSize}");
        $this->newLine();

        if ($totalPending === 0) {
            $this->warn("No pending URLs. Run 'scrape:discover {$marketplace}' first.");

            return self::SUCCESS;
        }

        $totalScraped = 0;
        $startTime = time();

        do {
            $scraped = $scraper->scrapeNextBatch($batchSize, $this);
            $totalScraped += $scraped;

            $remaining = ScrapeUrl::marketplace($marketplace)->pending()->count();
            $elapsed = time() - $startTime;
            $rate = $elapsed > 0 ? round($totalScraped / ($elapsed / 3600), 1) : 0;

            $this->info("--- Batch complete: +{$scraped} products ({$totalScraped} total this run) | {$remaining} pending | {$rate}/hr ---");

            // Rate limiting between requests is handled by BaseScraper::enforceRateLimit()
        } while ($loop && $remaining > 0);

        $elapsed = time() - $startTime;
        $this->newLine();
        $this->info('=== Done ===');
        $this->info("  Scraped {$totalScraped} products in ".gmdate('H:i:s', $elapsed));
        $this->info("  Remaining: {$remaining} pending");

        return self::SUCCESS;
    }
}
