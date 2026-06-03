<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Support\VegaImageUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CatalogFixVegaImages extends Command
{
    protected $signature = 'catalog:fix-vega-images
                            {--dry-run : Print counts without writing}
                            {--limit=0 : Max rows to process (0 = all)}
                            {--ids= : Comma-separated product ids for pilot run}
                            {--verify : HEAD-check up to 20 fixed URLs}';

    protected $description = 'Normalize Vega image URLs from stale OpenCart cache paths to canonical source paths';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $verify = $this->option('verify');

        $query = ScrapedProduct::query()->marketplace('vega');

        $idsOpt = (string) $this->option('ids');
        if ($idsOpt !== '') {
            $ids = array_values(array_filter(
                array_map('intval', explode(',', $idsOpt)),
                fn ($n) => $n > 0,
            ));
            if ($ids === []) {
                $this->error('--ids must contain at least one positive integer.');

                return self::FAILURE;
            }
            $query->whereIn('id', $ids);
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = 0;
        $changed = 0;
        $verifyUrls = [];

        $query->orderBy('id')->chunkById(200, function ($rows) use ($dryRun, $verify, &$total, &$changed, &$verifyUrls) {
            foreach ($rows as $product) {
                $total++;
                $dirty = false;

                $fixedMain = VegaImageUrl::normalize($product->main_image_url);
                if ($fixedMain !== $product->main_image_url) {
                    $product->main_image_url = $fixedMain;
                    $dirty = true;
                }

                $fixedImages = VegaImageUrl::normalizeArray($product->images);
                if ($fixedImages !== $product->images) {
                    $product->images = $fixedImages;
                    $dirty = true;
                }

                $fixedCutout = VegaImageUrl::normalize($product->cutout_image_url);
                if ($fixedCutout !== $product->cutout_image_url) {
                    $product->cutout_image_url = $fixedCutout;
                    $dirty = true;
                }

                if ($dirty) {
                    $changed++;
                    if (! $dryRun) {
                        $product->saveQuietly();
                    }
                    if ($verify && count($verifyUrls) < 20 && $fixedMain) {
                        $verifyUrls[$product->id] = $fixedMain;
                    }
                }
            }

            $label = $dryRun ? 'Would update' : 'Updated';
            $this->info("{$label} {$changed}/{$total} rows…");
        });

        $this->newLine();
        $label = $dryRun ? 'Would update' : 'Updated';
        $this->info("Done. {$label} {$changed} of {$total} Vega products.");

        if ($verify && $verifyUrls !== []) {
            $this->newLine();
            $this->info('Verifying up to '.count($verifyUrls).' fixed image URLs…');
            $ok = 0;
            $fail = 0;
            foreach ($verifyUrls as $id => $url) {
                try {
                    $status = Http::timeout(10)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0', 'Accept' => 'image/*'])
                        ->head($url)
                        ->status();
                } catch (\Throwable) {
                    $status = 0;
                }
                if ($status === 200) {
                    $ok++;
                } else {
                    $fail++;
                    $this->warn("  FAIL #{$id}: HTTP {$status} — {$url}");
                }
            }
            $this->info("Verification: {$ok} OK, {$fail} failed out of ".count($verifyUrls).'.');
        }

        return self::SUCCESS;
    }
}
