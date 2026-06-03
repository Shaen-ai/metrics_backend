<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogQdrantSyncService;
use Illuminate\Console\Command;

class CatalogSyncQdrant extends Command
{
    protected $signature = 'catalog:sync-qdrant
                            {--marketplace= : Limit to marketplace}
                            {--prune-orphans : Remove Qdrant points with no matching active product}
                            {--delete-unavailable : Delete points for out-of-stock products instead of just updating payload}';

    protected $description = 'Sync scraped_products stock status to Qdrant payloads (no re-embed)';

    public function handle(CatalogQdrantSyncService $sync): int
    {
        $marketplace = $this->option('marketplace') ?: null;

        $this->info('=== Qdrant catalog sync ===');

        $deactivated = $sync->syncDeactivated($marketplace, $this);
        $reactivated = $sync->syncReactivated($marketplace, $this);

        $this->newLine();
        $this->info("Payload sync: {$deactivated} deactivated, {$reactivated} reactivated");

        if ($this->option('delete-unavailable')) {
            $this->newLine();
            $deleted = $sync->deleteUnavailable($marketplace, $this);
            $this->info("Deleted {$deleted} unavailable points from Qdrant");
        }

        if ($this->option('prune-orphans')) {
            $this->newLine();
            $pruned = $sync->pruneOrphans($this);
            $this->info("Pruned {$pruned} orphan points");
        }

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
