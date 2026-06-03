<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Services\Catalog\EmbeddingTextBuilder;
use App\Services\Catalog\OpenAiEmbeddingClient;
use App\Services\Catalog\QdrantCatalogClient;
use Illuminate\Console\Command;

class CatalogEmbedQdrant extends Command
{
    protected $signature = 'catalog:embed-qdrant
                            {--marketplace= : Limit to marketplace}
                            {--batch=32 : Embedding batch size}
                            {--only-missing : Only never embedded rows}
                            {--only-stale : Embed where embedded_at is null or product updated after last embed}
                            {--limit=0 : Max rows}';

    protected $description = 'Embed scraped_products into Qdrant catalog collection';

    public function handle(
        EmbeddingTextBuilder $builder,
        OpenAiEmbeddingClient $embedder,
        QdrantCatalogClient $qdrant,
    ): int {
        $qdrant->ensureCollection();
        $version = $builder->version();

        $query = ScrapedProduct::query()
            ->whereNotNull('product_family')
            ->whereNotNull('embedding_text')
            ->where('embedding_text_version', $version);

        if ($this->option('marketplace')) {
            $query->marketplace((string) $this->option('marketplace'));
        }
        if ($this->option('only-stale')) {
            $query->where(function ($q) {
                $q->whereNull('embedded_at')
                    ->orWhereColumn('updated_at', '>', 'embedded_at');
            });
        } elseif ($this->option('only-missing')) {
            $query->whereNull('embedded_at');
        }
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $batchSize = max(1, (int) $this->option('batch'));
        $total = 0;

        $query->orderBy('id')->chunkById($batchSize, function ($rows) use ($embedder, $qdrant, &$total) {
            $texts = $rows->pluck('embedding_text')->map(fn ($t) => (string) $t)->all();
            $vectors = $embedder->embedBatch($texts);
            $points = [];

            foreach ($rows as $i => $product) {
                $vector = $vectors[$i] ?? null;
                if (! is_array($vector) || $vector === []) {
                    $this->warn("Skip product {$product->id}: no vector");

                    continue;
                }
                $points[] = [
                    'id' => (int) $product->id,
                    'vector' => $vector,
                    'payload' => [
                        'product_id' => (int) $product->id,
                        'product_family' => (string) $product->product_family,
                        'product_subtype' => (string) ($product->product_subtype ?? ''),
                        'in_stock' => (bool) $product->in_stock,
                        'source_marketplace' => (string) $product->source_marketplace,
                        'embedding_text_version' => (string) $product->embedding_text_version,
                        'priority' => (int) ($product->priority ?? 0),
                    ],
                ];
                $product->embedded_at = now();
                $product->save();
            }

            $qdrant->upsertPoints($points);
            $total += count($points);
            $this->info("Upserted {$total} vectors to Qdrant…");
        });

        $this->info("Done. {$total} products embedded.");

        return self::SUCCESS;
    }
}
