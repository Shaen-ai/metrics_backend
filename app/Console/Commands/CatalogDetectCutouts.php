<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Services\Catalog\ProductCutoutDetector;
use Illuminate\Console\Command;

class CatalogDetectCutouts extends Command
{
    protected $signature = 'catalog:detect-cutouts
                            {--marketplace= : Limit to marketplace}
                            {--only-missing : Only rows without cutout_image_url}
                            {--limit=0 : Max rows}';

    protected $description = 'Detect cutout (isolated product) image URLs from gallery';

    public function handle(ProductCutoutDetector $detector): int
    {
        $query = ScrapedProduct::query();
        if ($this->option('marketplace')) {
            $query->marketplace((string) $this->option('marketplace'));
        }
        if ($this->option('only-missing')) {
            $query->whereNull('cutout_image_url');
        }
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $count = 0;
        $query->orderBy('id')->chunkById(100, function ($rows) use ($detector, &$count) {
            foreach ($rows as $product) {
                $result = $detector->detect($product);
                $product->cutout_image_url = $result['url'];
                $product->cutout_confidence = $result['confidence'];
                $product->save();
                $count++;
            }
            $this->info("Processed {$count} products…");
        });

        $this->info("Done. {$count} cutouts detected.");

        return self::SUCCESS;
    }
}
