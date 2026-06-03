<?php

namespace App\Console\Commands;

use App\Models\ScrapedProduct;
use App\Services\Catalog\ProductTaxonomyAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CatalogAuditTaxonomy extends Command
{
    protected $signature = 'catalog:audit-taxonomy
                            {--marketplace= : vega, domus, or jysk}
                            {--ids= : Comma-separated product ids}
                            {--limit=0 : Max rows to scan (0 = all)}
                            {--format=table : Output format: table or json}
                            {--fail-on-anomaly : Exit with code 1 if any anomaly found}';

    protected $description = 'Audit scraped_products for taxonomy mismatches (read-only, no DB writes)';

    public function handle(ProductTaxonomyAudit $audit): int
    {
        $query = ScrapedProduct::query()->whereNotNull('product_family');

        if ($this->option('marketplace')) {
            $query->marketplace((string) $this->option('marketplace'));
        }

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
        $result = $audit->scan($query, $limit);
        $anomalies = $result['anomalies'];
        $total = $result['total_scanned'];

        foreach ($anomalies as $row) {
            Log::warning('catalog.taxonomy_anomaly', $row);
        }

        $format = (string) $this->option('format');
        if ($format === 'json') {
            $this->line(json_encode([
                'total_scanned' => $total,
                'anomaly_count' => count($anomalies),
                'anomalies' => $anomalies,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info("Scanned {$total} products.");

            if ($anomalies === []) {
                $this->info('No anomalies found.');
            } else {
                $counts = [];
                foreach ($anomalies as $a) {
                    $counts[$a['reason']] = ($counts[$a['reason']] ?? 0) + 1;
                }
                foreach ($counts as $reason => $count) {
                    $this->warn("  {$reason}: {$count}");
                }

                $this->table(
                    ['ID', 'Name', 'Reason', 'Stored', 'Expected'],
                    array_map(fn ($a) => [
                        $a['product_id'],
                        mb_substr($a['name'], 0, 50),
                        $a['reason'],
                        ($a['stored_family'] ?? '-').'/'.($a['stored_subtype'] ?? '-'),
                        ($a['expected_family'] ?? '-').'/'.($a['expected_subtype'] ?? '-'),
                    ], array_slice($anomalies, 0, 100)),
                );

                if (count($anomalies) > 100) {
                    $this->info('(showing first 100 of '.count($anomalies).' anomalies)');
                }
            }
        }

        if ($this->option('fail-on-anomaly') && count($anomalies) > 0) {
            $this->error(count($anomalies).' anomalies found.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
