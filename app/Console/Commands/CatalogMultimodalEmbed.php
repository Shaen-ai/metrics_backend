<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Services\Catalog\OpenAiEmbeddingClient;
use App\Services\Catalog\QdrantCatalogClient;
use Illuminate\Console\Command;

/**
 * Phase 3: multimodal-style image vectors (text proxy from cutout + product until native image embed API).
 */
class CatalogMultimodalEmbed extends Command
{
    protected $signature = 'catalog:multimodal-embed
                            {--marketplace= : Limit to marketplace}
                            {--only-missing : Only rows without image vector in Qdrant}
                            {--limit=0 : Max rows}';

    protected $description = 'Phase 3: embed cutout-aware product text into Qdrant image_vectors collection';

    public function handle(OpenAiEmbeddingClient $embedder, QdrantCatalogClient $qdrant): int
    {
        if (! config('catalog.multimodal.enabled', false)) {
            $this->warn('Set CATALOG_MULTIMODAL_ENABLED=true to run multimodal embedding.');

            return self::SUCCESS;
        }

        $qdrant->ensureMultimodalCollection();

        $query = ScrapedProduct::query()
            ->whereNotNull('product_family')
            ->whereNotNull('cutout_image_url');

        if ($this->option('marketplace')) {
            $query->marketplace((string) $this->option('marketplace'));
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $count = 0;
        $query->orderBy('id')->chunkById(32, function ($rows) use ($embedder, $qdrant, &$count) {
            $texts = [];
            /** @var array<int, ScrapedProduct> $batch */
            $batch = [];
            foreach ($rows as $product) {
                $texts[] = $this->multimodalText($product);
                $batch[] = $product;
            }
            $vectors = $embedder->embedBatch($texts);
            $points = [];
            foreach ($batch as $i => $product) {
                $vector = $vectors[$i] ?? null;
                if (! is_array($vector) || $vector === []) {
                    continue;
                }
                $points[] = [
                    'id' => (int) $product->id,
                    'vector' => $vector,
                    'payload' => [
                        'product_id' => (int) $product->id,
                        'product_family' => (string) $product->product_family,
                        'product_subtype' => (string) ($product->product_subtype ?? ''),
                        'cutout_image_url' => (string) $product->cutout_image_url,
                    ],
                ];
            }
            $qdrant->upsertMultimodalPoints($points);
            $count += count($points);
            $this->info("Multimodal upserted {$count}…");
        });

        $this->info("Done. {$count} multimodal vectors.");

        return self::SUCCESS;
    }

    private function multimodalText(ScrapedProduct $product): string
    {
        $tags = is_array($product->ai_tags) ? $product->ai_tags : [];
        $styles = implode(', ', $tags['styles'] ?? []);
        $colors = implode(', ', $tags['colors'] ?? []);

        return trim(implode("\n", array_filter([
            'Isolated product cutout image.',
            $product->name_en ?: $product->name,
            $product->product_family.' / '.$product->product_subtype,
            $styles ? 'styles: '.$styles : null,
            $colors ? 'colors: '.$colors : null,
            'cutout: '.$product->cutout_image_url,
        ])));
    }
}
