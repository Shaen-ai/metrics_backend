<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Services\Catalog\QdrantCatalogClient;
use Illuminate\Console\Command;

class CatalogSyncPriority extends Command
{
    protected $signature = 'catalog:sync-priority
                            {--marketplace= : Limit to marketplace}';

    protected $description = 'Sync scraped_products.priority values to Qdrant payloads (no re-embed)';

    public function handle(QdrantCatalogClient $qdrant): int
    {
        $query = ScrapedProduct::query()
            ->whereNotNull('embedded_at')
            ->where('in_stock', true);

        if ($this->option('marketplace')) {
            $query->marketplace((string) $this->option('marketplace'));
        }

        $synced = 0;

        $query->select(['id', 'priority'])->chunkById(200, function ($products) use ($qdrant, &$synced) {
            $ids = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
            $priorities = $products->pluck('priority', 'id');

            $grouped = [];
            foreach ($ids as $id) {
                $p = (int) ($priorities[$id] ?? 0);
                $grouped[$p][] = $id;
            }

            foreach ($grouped as $priority => $groupIds) {
                $qdrant->setPayload($groupIds, ['priority' => $priority]);
            }

            $synced += count($ids);
            $this->info("Synced priority for {$synced} products…");
        });

        $this->info("Done. {$synced} products synced.");

        return self::SUCCESS;
    }
}
