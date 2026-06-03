<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Models\ScrapeUrl;
use Illuminate\Console\Command;

class ScrapeStatus extends Command
{
    protected $signature = 'scrape:status';

    protected $description = 'Show current scraping progress for all marketplaces';

    public function handle(): int
    {
        foreach (['vega', 'domus', 'jysk'] as $marketplace) {
            $this->info("\n=== {$marketplace} ===");

            $urls = ScrapeUrl::marketplace($marketplace);
            $this->table(
                ['Status', 'Count'],
                [
                    ['Total URLs', $urls->count()],
                    ['Pending', (clone $urls)->pending()->count()],
                    ['Done', (clone $urls)->where('status', 'done')->count()],
                    ['Failed', (clone $urls)->failed()->count()],
                ]
            );

            $products = ScrapedProduct::marketplace($marketplace);
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Products', $products->count()],
                    ['With Dimensions', (clone $products)->withDimensions()->count()],
                    ['In Stock', (clone $products)->inStock()->count()],
                    ['Avg Price (AMD)', (int) (clone $products)->where('price', '>', 0)->avg('price')],
                ]
            );
        }

        return self::SUCCESS;
    }
}
