<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Services\Catalog\ProductTaxonomyAudit;
use App\Services\Catalog\ProductTaxonomyClassifier;
use Illuminate\Console\Command;

class CatalogBackfillTaxonomy extends Command
{
    protected $signature = 'catalog:backfill-taxonomy
                            {--marketplace= : vega, domus, or jysk}
                            {--only-missing : Only rows without product_family}
                            {--fix-drift : Only rows where classifier disagrees with stored taxonomy}
                            {--reclassify : Re-run classifier on ALL matched rows}
                            {--dry-run : Show what would change without writing to DB}
                            {--ids= : Re-classify a specific comma-separated list of product ids (e.g. 15868,18909)}
                            {--limit=0 : Max rows (0 = all)}';

    protected $description = 'Backfill product_family, product_subtype, and tags on scraped_products';

    public function handle(ProductTaxonomyClassifier $classifier, ProductTaxonomyAudit $audit): int
    {
        $query = ScrapedProduct::query();
        if ($this->option('marketplace')) {
            $query->marketplace((string) $this->option('marketplace'));
        }

        $idsOpt = (string) $this->option('ids');
        if ($idsOpt !== '') {
            $ids = array_values(array_filter(
                array_map('intval', explode(',', $idsOpt)),
                fn ($n) => $n > 0,
            ));
            if ($ids === []) {
                $this->error('--ids must contain at least one positive integer.');

                return self::FAILURE;
            }
            $query->whereIn('id', $ids);
        } elseif ($this->option('only-missing')) {
            $query->whereNull('product_family');
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $fixDrift = (bool) $this->option('fix-drift');
        $reclassify = (bool) $this->option('reclassify');
        $dryRun = (bool) $this->option('dry-run');

        $updated = 0;
        $skipped = 0;
        $invalidated = 0;
        $query->orderBy('id')->chunkById(200, function ($rows) use ($classifier, $audit, $fixDrift, $reclassify, $dryRun, &$updated, &$skipped, &$invalidated) {
            foreach ($rows as $product) {
                if ($fixDrift && ! $reclassify) {
                    $anomaly = $audit->checkRow($product);
                    if ($anomaly === null) {
                        $skipped++;

                        continue;
                    }
                }

                $classified = $classifier->classify(
                    (string) $product->name,
                    $product->category_en,
                    $product->category,
                );

                if (! $reclassify && ! $fixDrift) {
                    // Default mode (--only-missing or --ids): always apply
                }

                $familyChanged = $product->product_family !== $classified['product_family'];
                $subtypeChanged = $product->product_subtype !== $classified['product_subtype'];
                $taxonomyChanged = $familyChanged || $subtypeChanged;

                if ($dryRun) {
                    if ($taxonomyChanged) {
                        $this->line(sprintf(
                            'ID %d: %s => %s/%s (was %s/%s)',
                            $product->id,
                            mb_substr($product->name, 0, 50),
                            $classified['product_family'] ?? '-',
                            $classified['product_subtype'] ?? '-',
                            $product->product_family ?? '-',
                            $product->product_subtype ?? '-',
                        ));
                        $updated++;
                    } else {
                        $skipped++;
                    }

                    continue;
                }

                $product->product_family = $classified['product_family'];
                $product->product_subtype = $classified['product_subtype'];
                $product->material_tags = $classified['material_tags'];
                $product->color_tags = $classified['color_tags'];

                if ($taxonomyChanged && $product->embedded_at !== null) {
                    $product->embedded_at = null;
                    $invalidated++;
                }

                $product->save();
                $updated++;
            }
            $this->info("Updated {$updated} rows".($skipped > 0 ? ", skipped {$skipped}" : '').'…');
        });

        $prefix = $dryRun ? 'Dry run: would update' : 'Done. Updated';
        $this->info("{$prefix} {$updated} products".($skipped > 0 ? ", skipped {$skipped}" : '').'.');
        if ($invalidated > 0) {
            $this->info("Invalidated embedded_at on {$invalidated} rows (re-embed needed: catalog:build-embedding-text + catalog:embed-qdrant --only-stale).");
        }

        return self::SUCCESS;
    }
}
