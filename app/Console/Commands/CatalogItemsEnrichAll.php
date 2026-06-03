<?php

namespace App\Console\Commands;

use App\Models\CatalogItem;
use App\Services\Catalog\CatalogItemEmbeddingTextBuilder;
use App\Services\Catalog\CatalogItemEnricher;
use Illuminate\Console\Command;

class CatalogItemsEnrichAll extends Command
{
    protected $signature = 'catalog-items:enrich-all
                            {--admin= : Limit to admin UUID}
                            {--only-missing : Skip already enriched}
                            {--limit=0 : Max rows}';

    protected $description = 'AI-enrich all catalog items (product_family, subtypes, ai_tags)';

    public function handle(CatalogItemEnricher $enricher, CatalogItemEmbeddingTextBuilder $textBuilder): int
    {
        $query = CatalogItem::with('images')->where('is_active', true);

        if ($this->option('only-missing')) {
            $query->whereNull('product_family');
        }
        if ($this->option('admin')) {
            $query->where('admin_id', (string) $this->option('admin'));
        }
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $count = 0;
        $errors = 0;

        $query->orderBy('id')->chunkById(20, function ($rows) use ($enricher, $textBuilder, &$count, &$errors) {
            foreach ($rows as $item) {
                try {
                    $result = $enricher->enrich($item);

                    if ($item->product_family === null) {
                        $item->product_family = $result['product_family'];
                    }
                    $item->product_subtype = $result['product_subtype'];
                    $item->material_tags = $result['material_tags'];
                    $item->color_tags = $result['color_tags'];
                    $item->ai_tags = $result['ai_tags'];
                    $item->ai_enriched_at = now();

                    $item->embedding_text = $textBuilder->build($item);
                    $item->embedding_text_version = $textBuilder->version();
                    $item->embedded_at = null;

                    $item->save();
                    $count++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->warn("Item {$item->id}: {$e->getMessage()}");
                }
            }
            $this->info("Enriched {$count} items (errors: {$errors})…");
            usleep(200_000);
        });

        $this->info("Done. {$count} enriched, {$errors} errors.");

        return $errors > 0 && $count === 0 ? self::FAILURE : self::SUCCESS;
    }
}
