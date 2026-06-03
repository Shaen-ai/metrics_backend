<?php

namespace App\Observers;

use App\Jobs\CatalogItemEnrichAndEmbedJob;
use App\Models\CatalogItem;
use App\Services\Catalog\CatalogItemQdrantClient;

class CatalogItemObserver
{
    public function created(CatalogItem $item): void
    {
        CatalogItemEnrichAndEmbedJob::dispatch($item->id);
    }

    public function updated(CatalogItem $item): void
    {
        CatalogItemEnrichAndEmbedJob::dispatch($item->id);
    }

    public function deleted(CatalogItem $item): void
    {
        app(CatalogItemQdrantClient::class)->deleteProduct($item->id);
    }
}
