<?php

namespace App\Console\Commands;

use App\Services\Catalog\QdrantCatalogClient;
use Illuminate\Console\Command;

class CatalogSetupQdrant extends Command
{
    protected $signature = 'catalog:setup-qdrant {--multimodal : Also create multimodal collection}';

    protected $description = 'Create Qdrant catalog collections and payload indexes';

    public function handle(QdrantCatalogClient $qdrant): int
    {
        try {
            $qdrant->ensureCollection();
            $this->info('Primary catalog collection ready.');
            if ($this->option('multimodal') || config('catalog.multimodal.enabled')) {
                $qdrant->ensureMultimodalCollection();
                $this->info('Multimodal collection ready.');
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
