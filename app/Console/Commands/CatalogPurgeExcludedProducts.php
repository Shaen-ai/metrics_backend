<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Services\Catalog\ExcludedScrapedProduct;
use App\Services\Catalog\QdrantCatalogClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CatalogPurgeExcludedProducts extends Command
{
    protected $signature = 'catalog:purge-excluded-products
                            {--dry-run : Report counts without deleting}';

    protected $description = 'Hard-delete excluded scraped products from DB and Qdrant; strip catalog-hidden towel fixtures from Qdrant';

    public function handle(QdrantCatalogClient $qdrant): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $excludedMatched = 0;
        $excludedQdrantDeleted = 0;
        $excludedDbDeleted = 0;
        $hiddenMatched = 0;
        $hiddenQdrantStripped = 0;
        $hiddenFamilyNulled = 0;

        ScrapedProduct::query()
            ->orderBy('id')
            ->chunkById(200, function ($products) use (
                $qdrant,
                $dryRun,
                &$excludedMatched,
                &$excludedQdrantDeleted,
                &$excludedDbDeleted,
                &$hiddenMatched,
                &$hiddenQdrantStripped,
                &$hiddenFamilyNulled,
            ) {
                $excludedToDelete = [];
                $excludedQdrantIds = [];
                $hiddenIds = [];
                $hiddenQdrantIds = [];

                foreach ($products as $product) {
                    $name = (string) $product->name;
                    $nameEn = $product->name_en !== null ? (string) $product->name_en : null;
                    $category = $product->category !== null ? (string) $product->category : null;
                    $categoryEn = $product->category_en !== null ? (string) $product->category_en : null;

                    if (ExcludedScrapedProduct::isExcluded($name, $nameEn, $category, $categoryEn)) {
                        $excludedMatched++;
                        $excludedToDelete[] = $product->id;
                        if ($product->embedded_at !== null) {
                            $excludedQdrantIds[] = $product->id;
                        }

                        continue;
                    }

                    if (ExcludedScrapedProduct::isCatalogHidden($name, $nameEn, $category, $categoryEn)) {
                        $hiddenMatched++;
                        $hiddenIds[] = $product->id;
                        if ($product->embedded_at !== null) {
                            $hiddenQdrantIds[] = $product->id;
                        }
                    }
                }

                if ($excludedQdrantIds !== []) {
                    $excludedQdrantDeleted += count($excludedQdrantIds);
                    if (! $dryRun) {
                        $this->deleteQdrantPoints($qdrant, $excludedQdrantIds, 'excluded');
                    }
                }

                if ($hiddenQdrantIds !== []) {
                    $hiddenQdrantStripped += count($hiddenQdrantIds);
                    if (! $dryRun) {
                        $this->deleteQdrantPoints($qdrant, $hiddenQdrantIds, 'hidden');
                    }
                }

                if ($hiddenIds !== [] && ! $dryRun) {
                    $updated = ScrapedProduct::whereIn('id', $hiddenIds)->update([
                        'product_family' => null,
                        'product_subtype' => null,
                        'embedded_at' => null,
                    ]);
                    $hiddenFamilyNulled += $updated;
                }

                if ($excludedToDelete !== [] && ! $dryRun) {
                    $deleted = ScrapedProduct::whereIn('id', $excludedToDelete)->delete();
                    $excludedDbDeleted += $deleted;
                }
            });

        $this->info('=== Purge excluded scraped products ===');
        $this->line("Excluded matched: {$excludedMatched}");
        $this->line("Excluded Qdrant points removed: {$excludedQdrantDeleted}");
        $this->line('Excluded DB rows deleted: '.($dryRun ? "{$excludedMatched} (dry-run)" : (string) $excludedDbDeleted));
        $this->line("Catalog-hidden matched: {$hiddenMatched}");
        $this->line("Catalog-hidden Qdrant points stripped: {$hiddenQdrantStripped}");
        $this->line('Catalog-hidden family nulled: '.($dryRun ? "{$hiddenMatched} (dry-run)" : (string) $hiddenFamilyNulled));

        if ($dryRun) {
            $this->warn('Dry run — no changes written.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $ids
     */
    private function deleteQdrantPoints(QdrantCatalogClient $qdrant, array $ids, string $label): void
    {
        try {
            $qdrant->deleteProducts($ids);
        } catch (\Throwable $e) {
            Log::warning("catalog.purge_{$label}_qdrant_failed", [
                'ids' => $ids,
                'error' => $e->getMessage(),
            ]);
            $this->error("Qdrant delete failed for {$label} batch: ".$e->getMessage());
        }
    }
}
