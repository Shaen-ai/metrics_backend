<?php

namespace App\Services\Catalog;

use App\Models\ScrapedProduct;
use Illuminate\Console\Command;

class CatalogQdrantSyncService
{
    public function __construct(
        private QdrantCatalogClient $qdrant,
    ) {}

    /**
     * Sync in_stock=false payload for deactivated products still in Qdrant.
     */
    public function syncDeactivated(?string $marketplace = null, ?Command $command = null): int
    {
        $query = ScrapedProduct::query()
            ->whereNotNull('embedded_at')
            ->whereNotNull('unavailable_at')
            ->where('in_stock', false);

        if ($marketplace) {
            $query->marketplace($marketplace);
        }

        $synced = 0;
        $query->select(['id'])->chunkById(200, function ($products) use (&$synced, $command) {
            $ids = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->qdrant->setPayload($ids, ['in_stock' => false]);
            $synced += count($ids);
            $this->log("Synced in_stock=false for {$synced} points…", $command);
        });

        return $synced;
    }

    /**
     * Sync in_stock=true payload for reactivated products.
     */
    public function syncReactivated(?string $marketplace = null, ?Command $command = null): int
    {
        $query = ScrapedProduct::query()
            ->whereNotNull('embedded_at')
            ->where('in_stock', true)
            ->whereNull('unavailable_at');

        if ($marketplace) {
            $query->marketplace($marketplace);
        }

        $synced = 0;
        $query->select(['id'])->chunkById(200, function ($products) use (&$synced, $command) {
            $ids = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->qdrant->setPayload($ids, ['in_stock' => true]);
            $synced += count($ids);
            $this->log("Synced in_stock=true for {$synced} points…", $command);
        });

        return $synced;
    }

    /**
     * Delete Qdrant points for unavailable products.
     */
    public function deleteUnavailable(?string $marketplace = null, ?Command $command = null): int
    {
        $query = ScrapedProduct::query()
            ->whereNotNull('embedded_at')
            ->where('in_stock', false);

        if ($marketplace) {
            $query->marketplace($marketplace);
        }

        $deleted = 0;
        $query->select(['id'])->chunkById(200, function ($products) use (&$deleted, $command) {
            $ids = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->qdrant->deleteProducts($ids);
            ScrapedProduct::whereIn('id', $ids)->update(['embedded_at' => null]);
            $deleted += count($ids);
            $this->log("Deleted {$deleted} unavailable points from Qdrant…", $command);
        });

        return $deleted;
    }

    /**
     * Remove orphan Qdrant points that no longer have a matching active product in MySQL.
     */
    public function pruneOrphans(?Command $command = null): int
    {
        $this->log('Scrolling all Qdrant point IDs…', $command);
        $qdrantIds = $this->qdrant->scrollAllIds();

        if (empty($qdrantIds)) {
            $this->log('No points in Qdrant collection', $command);

            return 0;
        }

        $existingIds = ScrapedProduct::whereIn('id', $qdrantIds)
            ->where('in_stock', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $orphanIds = array_values(array_diff($qdrantIds, $existingIds));

        if (empty($orphanIds)) {
            $this->log('No orphan points found', $command);

            return 0;
        }

        foreach (array_chunk($orphanIds, 200) as $chunk) {
            $this->qdrant->deleteProducts($chunk);
        }

        $this->log('Pruned '.count($orphanIds).' orphan points from Qdrant', $command);

        return count($orphanIds);
    }

    private function log(string $message, ?Command $command): void
    {
        if ($command) {
            $command->info($message);
        }
    }
}
