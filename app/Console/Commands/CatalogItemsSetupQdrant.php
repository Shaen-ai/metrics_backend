<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogItemQdrantClient;
use Illuminate\Console\Command;

class CatalogItemsSetupQdrant extends Command
{
    protected $signature = 'catalog-items:setup-qdrant';

    protected $description = 'Create Qdrant collection for merchant catalog items';

    public function handle(CatalogItemQdrantClient $qdrant): int
    {
        try {
            $qdrant->ensureCollection();
            $this->info('Catalog items collection ready.');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
