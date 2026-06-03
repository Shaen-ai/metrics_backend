<?php

namespace App\Jobs;

use App\Models\CatalogItem;
use App\Services\Catalog\CatalogItemEmbeddingTextBuilder;
use App\Services\Catalog\CatalogItemEnricher;
use App\Services\Catalog\CatalogItemQdrantClient;
use App\Services\Catalog\OpenAiEmbeddingClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CatalogItemEnrichAndEmbedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $catalogItemId,
    ) {}

    public function handle(
        CatalogItemEnricher $enricher,
        CatalogItemEmbeddingTextBuilder $textBuilder,
        OpenAiEmbeddingClient $embedder,
        CatalogItemQdrantClient $qdrant,
    ): void {
        $item = CatalogItem::with('images')->find($this->catalogItemId);

        if (! $item || ! $item->is_active) {
            $qdrant->deleteProduct($this->catalogItemId);

            return;
        }

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

            $embeddingText = $textBuilder->build($item);
            $item->embedding_text = $embeddingText;
            $item->embedding_text_version = $textBuilder->version();
            $item->save();

            $vector = $embedder->embed($embeddingText);
            if ($vector === []) {
                Log::warning("CatalogItemEnrichAndEmbedJob: empty vector for item {$this->catalogItemId}");

                return;
            }

            $qdrant->upsertPoints([
                [
                    'id' => $item->id,
                    'vector' => $vector,
                    'payload' => [
                        'product_id' => $item->id,
                        'admin_id' => $item->admin_id,
                        'product_family' => (string) $item->product_family,
                        'product_subtype' => (string) ($item->product_subtype ?? ''),
                        'in_stock' => (bool) $item->is_active,
                    ],
                ],
            ]);

            $item->embedded_at = now();
            $item->save();
        } catch (\Throwable $e) {
            Log::error("CatalogItemEnrichAndEmbedJob failed for {$this->catalogItemId}: {$e->getMessage()}");

            throw $e;
        }
    }
}
