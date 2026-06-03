<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Services\Catalog\EmbeddingTextBuilder;
use App\Services\Catalog\ProductCutoutDetector;
use Illuminate\Console\Command;

class CatalogBuildEmbeddingText extends Command
{
    protected $signature = 'catalog:build-embedding-text
                            {--marketplace= : Limit to marketplace}
                            {--only-stale : Rebuild when version mismatch or empty}
                            {--limit=0 : Max rows}';

    protected $description = 'Build deterministic embedding_text (+ cutout fields) for scraped_products';

    public function handle(EmbeddingTextBuilder $builder, ProductCutoutDetector $cutouts): int
    {
        $version = $builder->version();
        $query = ScrapedProduct::query()->whereNotNull('product_family');
        if ($this->option('marketplace')) {
            $query->marketplace((string) $this->option('marketplace'));
        }
        if ($this->option('only-stale')) {
            $query->where(function ($q) use ($version) {
                $q->whereNull('embedding_text')
                    ->orWhere('embedding_text_version', '!=', $version);
            });
        }
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $count = 0;
        $invalidated = 0;
        $query->orderBy('id')->chunkById(200, function ($rows) use ($builder, $cutouts, $version, &$count, &$invalidated) {
            foreach ($rows as $product) {
                $newText = $builder->build($product);
                $textChanged = $product->embedding_text !== $newText;

                $product->embedding_text = $newText;
                $product->embedding_text_version = $version;
                $cut = $cutouts->detect($product);
                $product->cutout_image_url = $cut['url'];
                $product->cutout_confidence = $cut['confidence'];

                if ($textChanged && $product->embedded_at !== null) {
                    $product->embedded_at = null;
                    $invalidated++;
                }

                $product->save();
                $count++;
            }
            $this->info("Built embedding_text for {$count} products…");
        });

        $this->info("Done. {$count} rows updated, {$invalidated} embeddings invalidated for re-embed.");

        return self::SUCCESS;
    }
}
