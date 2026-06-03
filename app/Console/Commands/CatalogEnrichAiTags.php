<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Services\Catalog\EmbeddingTextBuilder;
use App\Services\Catalog\GeminiCatalogEnricher;
use Illuminate\Console\Command;

class CatalogEnrichAiTags extends Command
{
    protected $signature = 'catalog:enrich-ai-tags
                            {--marketplace= : Limit to marketplace}
                            {--only-missing : Skip rows with ai_enriched_at set}
                            {--rebuild-text : Rebuild embedding_text after enrich}
                            {--limit=0 : Max rows}';

    protected $description = 'Phase 2: Gemini structured ai_tags enrichment for scraped_products';

    public function handle(GeminiCatalogEnricher $enricher, EmbeddingTextBuilder $builder): int
    {
        $query = ScrapedProduct::query()->whereNotNull('product_family');
        if ($this->option('marketplace')) {
            $query->marketplace((string) $this->option('marketplace'));
        }
        if ($this->option('only-missing')) {
            $query->whereNull('ai_enriched_at');
        }
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $count = 0;
        $errors = 0;

        $query->orderBy('id')->chunkById(20, function ($rows) use ($enricher, $builder, &$count, &$errors) {
            foreach ($rows as $product) {
                try {
                    $tags = $enricher->enrich($product);
                    $product->ai_tags = $tags;
                    $product->ai_enriched_at = now();
                    if ($this->option('rebuild-text')) {
                        $product->embedding_text = $builder->build($product);
                        $product->embedding_text_version = $builder->version();
                        $product->embedded_at = null;
                    }
                    $product->save();
                    $count++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->warn("Product {$product->id}: {$e->getMessage()}");
                }
            }
            $this->info("Enriched {$count} products (errors: {$errors})…");
            usleep(200_000);
        });

        $this->info("Done. {$count} enriched, {$errors} errors.");

        return $errors > 0 && $count === 0 ? self::FAILURE : self::SUCCESS;
    }
}
