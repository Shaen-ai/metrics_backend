<?php

namespace App\Console\Commands;

use App\Models\CatalogItem;
use App\Services\Catalog\CatalogItemQdrantClient;
use Illuminate\Console\Command;

class CatalogItemsSyncQdrant extends Command
{
    protected $signature = 'catalog-items:sync-qdrant
                            {--prune-orphans : Delete orphan Qdrant points not in DB}';

    protected $description = 'Sync catalog items status to Qdrant and optionally prune orphans';

    public function handle(CatalogItemQdrantClient $qdrant): int
    {
        $this->syncActiveStatus($qdrant);

        if ($this->option('prune-orphans')) {
            $this->pruneOrphans($qdrant);
        }

        return self::SUCCESS;
    }

    private function syncActiveStatus(CatalogItemQdrantClient $qdrant): void
    {
        $this->info('Syncing is_active → in_stock payload…');

        $updated = 0;

        CatalogItem::query()
            ->whereNotNull('embedded_at')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($qdrant, &$updated) {
                $activeIds = [];
                $inactiveIds = [];

                foreach ($rows as $item) {
                    if ($item->is_active) {
                        $activeIds[] = $item->id;
                    } else {
                        $inactiveIds[] = $item->id;
                    }
                }

                if ($activeIds !== []) {
                    $qdrant->setPayload($activeIds, ['in_stock' => true]);
                }
                if ($inactiveIds !== []) {
                    $qdrant->setPayload($inactiveIds, ['in_stock' => false]);
                }

                $updated += count($rows);
            });

        $this->info("Synced {$updated} items.");
    }

    private function pruneOrphans(CatalogItemQdrantClient $qdrant): void
    {
        $this->info('Scanning for orphan Qdrant points…');

        $qdrantIds = $qdrant->scrollAllIds();
        if ($qdrantIds === []) {
            $this->info('No points in collection.');

            return;
        }

        $dbIds = CatalogItem::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $dbIdSet = array_flip($dbIds);

        $orphans = array_filter($qdrantIds, fn ($id) => ! isset($dbIdSet[$id]));

        if ($orphans === []) {
            $this->info('No orphan points found.');

            return;
        }

        $this->info('Deleting '.count($orphans).' orphan points…');
        $qdrant->deleteProducts(array_values($orphans));
        $this->info('Orphans pruned.');
    }
}
