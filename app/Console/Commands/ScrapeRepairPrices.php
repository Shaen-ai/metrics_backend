<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Models\ScrapeUrl;
use App\Services\Scraper\DomusScraper;
use App\Services\Scraper\JyskScraper;
use App\Services\Scraper\VegaScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScrapeRepairPrices extends Command
{
    protected $signature = 'scrape:repair-prices
                            {marketplace : vega, domus or jysk}
                            {--min-price=1000000 : Only requeue products at or above this price}
                            {--prefix=11 : Only prices whose digit string starts with this prefix (qty-concat heuristic); empty = any}
                            {--delay=5 : Seconds between HTTP requests}
                            {--dry-run : Show how many products would be requeued, then exit}
                            {--requeue-only : Requeue matching URLs without scraping}
                            {--limit=0 : Cap how many products to requeue (0 = all)}';

    protected $description = 'Requeue and re-scrape marketplace products with corrupted/concatenated prices';

    public function handle(): int
    {
        $marketplace = $this->argument('marketplace');
        $minPrice = (int) $this->option('min-price');
        $prefix = (string) $this->option('prefix');
        $delay = (int) $this->option('delay');
        $limit = (int) $this->option('limit');

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

        $query = ScrapedProduct::query()
            ->marketplace($marketplace)
            ->where('price', '>=', $minPrice)
            ->whereNotNull('external_url')
            ->where('external_url', '!=', '');

        if ($prefix !== '') {
            $query->whereRaw('CAST(price AS CHAR) LIKE ?', [$prefix.'%']);
        }

        $query->orderByDesc('price');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $products = $query->get(['id', 'external_url', 'external_url_hash', 'price', 'name', 'name_en']);
        $count = $products->count();

        $this->info("=== {$marketplace} price repair ===");
        $this->info("  Match: price >= {$minPrice}".($prefix !== '' ? " AND starts with '{$prefix}'" : ''));
        $this->info("  Found: {$count} products");

        if ($count === 0) {
            $this->info('Nothing to repair.');

            return self::SUCCESS;
        }

        $sample = $products->take(5);
        foreach ($sample as $product) {
            $label = $product->name_en ?: $product->name;
            $this->line("  • id={$product->id} price={$product->price} — ".mb_substr((string) $label, 0, 60));
        }
        if ($count > 5) {
            $this->line('  • …');
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no URLs requeued.');

            return self::SUCCESS;
        }

        $requeued = 0;
        $created = 0;

        DB::transaction(function () use ($products, $marketplace, &$requeued, &$created) {
            foreach ($products as $product) {
                $hash = $product->external_url_hash ?: hash('sha256', $product->external_url);

                $existing = ScrapeUrl::query()
                    ->where('source_marketplace', $marketplace)
                    ->where('url_hash', $hash)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'url' => $product->external_url,
                        'status' => 'pending',
                        'fail_count' => 0,
                        'last_error' => null,
                    ]);
                    $requeued++;
                } else {
                    ScrapeUrl::create([
                        'source_marketplace' => $marketplace,
                        'url' => $product->external_url,
                        'url_hash' => $hash,
                        'status' => 'pending',
                        'discovery_source' => 'price_repair',
                    ]);
                    $created++;
                }
            }
        });

        $this->info("  Requeued: {$requeued} existing URLs, created: {$created} new URLs");

        if ($this->option('requeue-only')) {
            $this->info("Run: php artisan scrape:products {$marketplace} --loop");

            return self::SUCCESS;
        }

        $scraper->setDelaySeconds($delay);
        $pending = ScrapeUrl::marketplace($marketplace)->pending()->count();
        $this->info("  Pending now: {$pending}");
        $this->info("  Scraping with {$delay}s delay…");

        $totalScraped = 0;
        $startTime = time();

        while (ScrapeUrl::marketplace($marketplace)->pending()->exists()) {
            $scraped = $scraper->scrapeNextBatch(1, $this);
            $totalScraped += $scraped;
            $remaining = ScrapeUrl::marketplace($marketplace)->pending()->count();
            $elapsed = max(1, time() - $startTime);
            $rate = round($totalScraped / ($elapsed / 3600), 1);
            $this->info("--- +{$scraped} ({$totalScraped} this run) | {$remaining} pending | {$rate}/hr ---");
        }

        $badLeft = ScrapedProduct::query()
            ->marketplace($marketplace)
            ->where('price', '>=', $minPrice)
            ->when($prefix !== '', fn ($q) => $q->whereRaw('CAST(price AS CHAR) LIKE ?', [$prefix.'%']))
            ->count();

        $this->newLine();
        $this->info('=== Done ===');
        $this->info('  Scraped: '.$totalScraped.' in '.gmdate('H:i:s', time() - $startTime));
        $this->info("  Still matching filter: {$badLeft}");

        return self::SUCCESS;
    }
}
