<?php

namespace App\Console\Commands;

use App\Models\CatalogItem;
use App\Services\Catalog\CatalogItemEmbeddingTextBuilder;
use App\Services\Catalog\CatalogItemQdrantClient;
use App\Services\Catalog\OpenAiEmbeddingClient;
use Illuminate\Console\Command;

class CatalogItemsEmbedAll extends Command
{
    protected $signature = 'catalog-items:embed-all
                            {--admin= : Limit to admin UUID}
                            {--limit=0 : Max rows}';

    protected $description = 'Embed catalog items into Qdrant (processes items with product_family + embedding_text but no embedded_at)';

    public function handle(
        CatalogItemEmbeddingTextBuilder $textBuilder,
        OpenAiEmbeddingClient $embedder,
        CatalogItemQdrantClient $qdrant,
    ): int {
        $qdrant->ensureCollection();
        $version = $textBuilder->version();

        $query = CatalogItem::query()
            ->where('is_active', true)
            ->whereNotNull('product_family')
            ->whereNotNull('embedding_text')
            ->where('embedding_text_version', $version)
            ->whereNull('embedded_at');

        if ($this->option('admin')) {
            $query->where('admin_id', (string) $this->option('admin'));
        }
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = 0;

        $query->orderBy('id')->chunkById(20, function ($rows) use ($embedder, $qdrant, &$total) {
            $texts = $rows->pluck('embedding_text')->map(fn ($t) => (string) $t)->all();
            $vectors = $embedder->embedBatch($texts);
            $points = [];

            foreach ($rows as $i => $item) {
                $vector = $vectors[$i] ?? null;
                if (! is_array($vector) || $vector === []) {
                    $this->warn("Skip item {$item->id}: no vector");

                    continue;
                }
                $points[] = [
                    'id' => $item->id,
                    'vector' => $vector,
                    'payload' => [
                        'product_id' => $item->id,
                        'admin_id' => $item->admin_id,
                        'product_family' => (string) $item->product_family,
                        'product_subtype' => (string) ($item->product_subtype ?? ''),
                        'in_stock' => (bool) $item->is_active,
                    ],
                ];
                $item->embedded_at = now();
                $item->save();
            }

            if ($points !== []) {
                $qdrant->upsertPoints($points);
            }
            $total += count($points);
            $this->info("Upserted {$total} vectors to Qdrant…");
        });

        $this->info("Done. {$total} catalog items embedded.");

        return self::SUCCESS;
    }
}
