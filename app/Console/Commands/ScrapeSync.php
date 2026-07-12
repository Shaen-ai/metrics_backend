<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Models\ScrapeUrl;
use App\Services\Scraper\DomusScraper;
use App\Services\Scraper\JyskScraper;
use App\Services\Scraper\VegaScraper;
use Illuminate\Console\Command;

class ScrapeSync extends Command
{
    protected $signature = 'scrape:sync
                            {marketplace : vega, domus or jysk}
                            {--skip-discover : Skip URL discovery + reconciliation}
                            {--skip-scrape : Skip product scraping}
                            {--refresh-days=7 : Re-queue URLs scraped more than N days ago}
                            {--sync-qdrant : Run catalog:sync-qdrant at the end}
                            {--delete-unavailable : Delete unavailable products from Qdrant during sync}';

    protected $description = 'Full incremental sync: discover → refresh → scrape → Qdrant sync';

    public function handle(): int
    {
        $marketplace = $this->argument('marketplace');
        $refreshDays = (int) $this->option('refresh-days');

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

        $startTime = time();

        // 1. Discovery + reconciliation
        if (! $this->option('skip-discover')) {
            $sweepStartedAt = now();

            $this->info("=== [{$marketplace}] Step 1: Discovery ===");
            $newUrls = $scraper->discoverUrls($this);
            $this->info("Discovered {$newUrls} new URLs.");

            $this->newLine();
            $this->info('Reconciling listing removals…');
            $deactivated = $scraper->reconcileListingRemovals($sweepStartedAt, $this);
            $this->info("Reconciled: {$deactivated} products marked unavailable.");
        } else {
            $this->info("=== [{$marketplace}] Step 1: Discovery (SKIPPED) ===");
        }

        // 2. Refresh stale + scrape
        if (! $this->option('skip-scrape')) {
            $this->newLine();
            $this->info("=== [{$marketplace}] Step 2: Refresh + Scrape ===");

            $requeued = $scraper->requeueStaleUrls($refreshDays, $this);
            $this->info("Re-queued {$requeued} stale URLs (>{$refreshDays} days old).");

            // Vega historically concatenated qty digits into price; heal any leftovers
            // that the stale-day window would otherwise skip.
            if ($marketplace === 'vega') {
                $this->call('scrape:repair-prices', [
                    'marketplace' => 'vega',
                    '--requeue-only' => true,
                    '--min-price' => 1_000_000,
                    '--prefix' => '11',
                ]);
            }

            $pending = ScrapeUrl::marketplace($marketplace)->pending()->count();
            $this->info("Pending URLs: {$pending}");

            if ($pending > 0) {
                $this->info('Scraping pending URLs (10s rate limit per request)…');
                $totalScraped = 0;

                while (ScrapeUrl::marketplace($marketplace)->pending()->exists()) {
                    $scraped = $scraper->scrapeNextBatch(1, $this);
                    $totalScraped += $scraped;

                    $remaining = ScrapeUrl::marketplace($marketplace)->pending()->count();
                    $elapsed = time() - $startTime;
                    $rate = $elapsed > 0 ? round($totalScraped / ($elapsed / 3600), 1) : 0;
                    $this->info("--- +{$scraped} ({$totalScraped} total) | {$remaining} pending | {$rate}/hr ---");
                }

                $this->info("Scraping complete: {$totalScraped} products processed.");
            }
        } else {
            $this->newLine();
            $this->info("=== [{$marketplace}] Step 2: Scrape (SKIPPED) ===");
        }

        // 3. Qdrant sync
        if ($this->option('sync-qdrant')) {
            $this->newLine();
            $this->info("=== [{$marketplace}] Step 3: Qdrant Sync ===");
            $syncArgs = ['--marketplace' => $marketplace];
            if ($this->option('delete-unavailable')) {
                $syncArgs['--delete-unavailable'] = true;
            }
            $this->call('catalog:sync-qdrant', $syncArgs);
        }

        // Summary
        $this->newLine();
        $elapsed = time() - $startTime;
        $totalProducts = ScrapedProduct::marketplace($marketplace)->count();
        $inStock = ScrapedProduct::marketplace($marketplace)->inStock()->count();
        $outOfStock = $totalProducts - $inStock;

        $this->info("=== [{$marketplace}] Sync complete in ".gmdate('H:i:s', $elapsed).' ===');
        $this->info("  Products: {$totalProducts} total, {$inStock} in stock, {$outOfStock} unavailable");

        return self::SUCCESS;
    }
}
