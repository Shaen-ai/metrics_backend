<?php

namespace App\Services\Scraper;

use App\Models\ScrapedProduct;
use App\Models\ScrapeUrl;
use App\Services\Catalog\ExcludedScrapedProduct;
use App\Services\Catalog\ProductTaxonomyAudit;
use App\Services\Catalog\ProductTaxonomyClassifier;
use App\Services\Catalog\QdrantCatalogClient;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

abstract class BaseScraper
{
    protected Client $http;

    protected int $delaySeconds = 10;

    protected string $marketplace;

    /**
     * Timestamp of the last HTTP request to the marketplace.
     * Used to enforce minimum delay between ALL requests regardless of caller.
     */
    private float $lastRequestAt = 0;

    public function __construct()
    {
        $this->http = new Client([
            'timeout' => 30,
            'connect_timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
        ]);
    }

    abstract public function getMarketplace(): string;

    abstract public function discoverUrls(?Command $command = null, ?array $categoryFilter = null): int;

    abstract protected function parseProductPage(string $html, string $url): ?array;

    /**
     * Scrape one URL at a time with rate limiting.
     * Returns the number of products scraped.
     */
    public function scrapeNextBatch(int $batchSize, ?Command $command = null): int
    {
        $urls = ScrapeUrl::marketplace($this->getMarketplace())
            ->pending()
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        $scraped = 0;

        foreach ($urls as $index => $scrapeUrl) {
            try {
                $this->enforceRateLimit();
                $this->log("Fetching: {$scrapeUrl->url}", $command);
                $response = $this->http->get($scrapeUrl->url, [
                    'http_errors' => false,
                    'allow_redirects' => ['max' => 3, 'track_redirects' => true],
                ]);
                $this->lastRequestAt = microtime(true);

                $statusCode = $response->getStatusCode();

                if ($statusCode === 404 || $statusCode === 410) {
                    $scrapeUrl->markNotFound();
                    $this->markProductInactive($scrapeUrl);
                    $this->log("  GONE: HTTP {$statusCode} — product marked out of stock", $command);
                } elseif ($statusCode >= 400) {
                    $scrapeUrl->markFailed("HTTP {$statusCode}");
                    $this->log("  ERROR: HTTP {$statusCode}", $command, 'error');
                } else {
                    $html = (string) $response->getBody();

                    if ($this->isRedirectToNonProduct($response, $scrapeUrl->url)) {
                        $scrapeUrl->markNotFound();
                        $this->markProductInactive($scrapeUrl);
                        $this->log('  GONE: Redirected away — product marked out of stock', $command);
                    } elseif ($this->isEmptyOrErrorPage($html)) {
                        $scrapeUrl->markFailed('Empty or error page');
                        $this->log('  SKIP: Empty/error page', $command);
                    } else {
                        $data = $this->parseProductPage($html, $scrapeUrl->url);

                        if ($data === null) {
                            $scrapeUrl->markFailed('Could not parse product data — likely not a product page');
                            $this->log('  SKIP: No data parsed (not a real product)', $command);
                        } else {
                            $product = $this->upsertProduct($data, $scrapeUrl);
                            $scrapeUrl->markDone();
                            if ($product !== null) {
                                $scraped++;
                                $this->log("  OK: {$data['name']} — {$data['price']} AMD", $command);
                            } else {
                                $this->log('  SKIP: Excluded product type — purged if existed', $command);
                            }
                        }
                    }
                }
            } catch (ConnectException $e) {
                $scrapeUrl->markFailed('Connection timeout: '.$e->getMessage());
                $this->log("  TIMEOUT: {$e->getMessage()}", $command, 'error');
            } catch (\Throwable $e) {
                $scrapeUrl->markFailed($e->getMessage());
                $this->log("  ERROR: {$e->getMessage()}", $command, 'error');
            }

            // Rate limiting is handled by enforceRateLimit() before each request
        }

        return $scraped;
    }

    /**
     * When a product URL is confirmed gone (404, redirect), mark the
     * corresponding ScrapedProduct as out of stock so it's excluded from search.
     */
    protected function markProductInactive(ScrapeUrl $scrapeUrl): void
    {
        $urlHash = hash('sha256', $scrapeUrl->url);
        ScrapedProduct::where('source_marketplace', $this->getMarketplace())
            ->where('external_url_hash', $urlHash)
            ->where('in_stock', true)
            ->update([
                'in_stock' => false,
                'unavailable_at' => now(),
            ]);
    }

    /**
     * Reset already-scraped URLs back to pending so they get re-fetched.
     * This allows periodic price/stock updates and catches removed products.
     *
     * @param  int  $olderThanDays  Only re-queue URLs scraped more than N days ago
     */
    public function requeueStaleUrls(int $olderThanDays = 7, ?Command $command = null): int
    {
        $cutoff = now()->subDays($olderThanDays);

        $count = ScrapeUrl::where('source_marketplace', $this->getMarketplace())
            ->where('status', 'done')
            ->where('scraped_at', '<', $cutoff)
            ->update(['status' => 'pending']);

        $this->log("Re-queued {$count} stale URLs (older than {$olderThanDays} days)", $command);

        return $count;
    }

    /**
     * Record a URL found during listing discovery. Creates if new, always
     * touches last_seen_in_listing_at so reconciliation can detect removals.
     *
     * @return bool True if the URL was newly created (not seen before)
     */
    public function recordDiscoveredUrl(string $url, string $externalId = '', string $source = 'category'): bool
    {
        $urlHash = hash('sha256', $url);

        $scrapeUrl = ScrapeUrl::firstOrCreate(
            ['source_marketplace' => $this->getMarketplace(), 'url_hash' => $urlHash],
            [
                'url' => $url,
                'status' => 'pending',
                'external_id' => substr($externalId, 0, 128) ?: null,
                'discovery_source' => $source,
            ]
        );

        $scrapeUrl->update([
            'last_seen_in_listing_at' => now(),
            'discovery_source' => $source,
        ]);

        return $scrapeUrl->wasRecentlyCreated;
    }

    /**
     * Deactivate products whose URLs were NOT seen during the latest category
     * listing sweep. Only affects category-discovered, already-scraped URLs.
     *
     * Safety: aborts if deactivation would affect >50% of active marketplace products.
     */
    public function reconcileListingRemovals(Carbon $sweepStartedAt, ?Command $command = null): int
    {
        $marketplace = $this->getMarketplace();

        $staleQuery = ScrapeUrl::where('source_marketplace', $marketplace)
            ->where('discovery_source', 'category')
            ->where('status', 'done')
            ->where(function ($q) use ($sweepStartedAt) {
                $q->whereNull('last_seen_in_listing_at')
                    ->orWhere('last_seen_in_listing_at', '<', $sweepStartedAt);
            });

        $staleCount = $staleQuery->count();

        if ($staleCount === 0) {
            $this->log('Reconciliation: no stale URLs found', $command);

            return 0;
        }

        $activeProducts = ScrapedProduct::where('source_marketplace', $marketplace)
            ->where('in_stock', true)
            ->count();

        if ($activeProducts > 0 && ($staleCount / $activeProducts) > 0.5) {
            $this->log(
                "Reconciliation ABORTED: {$staleCount} stale URLs would deactivate >{$this->percent($staleCount, $activeProducts)}% of {$activeProducts} active products — possible crawl failure",
                $command,
                'error'
            );

            return 0;
        }

        $staleUrlHashes = $staleQuery->pluck('url_hash')->all();

        $deactivated = ScrapedProduct::where('source_marketplace', $marketplace)
            ->where('in_stock', true)
            ->whereIn('external_url_hash', $staleUrlHashes)
            ->update([
                'in_stock' => false,
                'unavailable_at' => now(),
            ]);

        $this->log("Reconciliation: deactivated {$deactivated} products (from {$staleCount} stale URLs)", $command);

        return $deactivated;
    }

    private function percent(int $part, int $total): string
    {
        return $total > 0 ? (string) round(($part / $total) * 100) : '0';
    }

    /**
     * Check if the response was redirected to a non-product page (e.g., homepage, category).
     * Marketplaces often redirect removed products to their homepage.
     */
    protected function isRedirectToNonProduct(ResponseInterface $response, string $originalUrl): bool
    {
        $redirects = $response->getHeader('X-Guzzle-Redirect-History');
        if (empty($redirects)) {
            return false;
        }

        $finalUrl = end($redirects);

        $originalPath = parse_url($originalUrl, PHP_URL_PATH);
        $finalPath = parse_url($finalUrl, PHP_URL_PATH);

        if ($finalPath === '/' || $finalPath === '/en/' || $finalPath === '/am/' || $finalPath === '/en' || $finalPath === '/am') {
            return true;
        }

        if ($originalPath !== $finalPath && ! str_ends_with($finalPath, '.html')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the HTML is too small or is an error page.
     */
    protected function isEmptyOrErrorPage(string $html): bool
    {
        return strlen($html) < 2000;
    }

    protected function upsertProduct(array $data, ScrapeUrl $scrapeUrl): ?ScrapedProduct
    {
        $externalUrl = $data['product_url'] ?? $scrapeUrl->url;
        unset($data['product_url']);

        $name = (string) ($data['name_en'] ?? $data['name'] ?? '');
        $category = isset($data['category']) ? (string) $data['category'] : null;
        $categoryEn = isset($data['category_en']) ? (string) $data['category_en'] : null;

        if (ExcludedScrapedProduct::isExcluded($name, $data['name_en'] ?? null, $category, $categoryEn)) {
            $this->purgeProductAtUrl($externalUrl);

            return null;
        }

        $data = app(ProductTaxonomyClassifier::class)->enrichPayload($data);

        // Name-first safety net: override taxonomy when the product name
        // clearly implies a different family than what enrichPayload produced
        // (e.g. merchant put "Sofa HOBEL" under "Ceiling lights").
        $anomaly = ProductTaxonomyAudit::anomalyForPayload(
            (string) ($data['name_en'] ?? $data['name'] ?? ''),
            $data['category_en'] ?? null,
            $data['product_family'] ?? null,
            $data['product_subtype'] ?? null,
        );
        if ($anomaly !== null) {
            Log::warning('catalog.taxonomy_scrape_override', [
                'name' => $data['name'] ?? '',
                'category_en' => $data['category_en'] ?? null,
                'was' => ($data['product_family'] ?? null).'/'.($data['product_subtype'] ?? null),
                'corrected_to' => $anomaly['expected_family'].'/'.($anomaly['expected_subtype'] ?? null),
                'reason' => $anomaly['reason'],
            ]);
            $data['product_family'] = $anomaly['expected_family'];
            $data['product_subtype'] = $anomaly['expected_subtype'];
        }

        $urlHash = hash('sha256', $externalUrl);

        $existing = ScrapedProduct::where('source_marketplace', $this->getMarketplace())
            ->where('external_url_hash', $urlHash)
            ->first();

        $payload = array_merge($data, [
            'source_marketplace' => $this->getMarketplace(),
            'external_url' => $externalUrl,
            'external_url_hash' => $urlHash,
            'scraped_at' => now(),
            'in_stock' => $data['in_stock'] ?? true,
            'unavailable_at' => null,
        ]);

        if ($existing && $this->hasMeaningfulChanges($existing, $payload)) {
            $payload['embedded_at'] = null;
        }

        // Preserve existing images when a re-scrape yields no images (partial parse failure)
        if ($existing) {
            if (empty($payload['main_image_url']) && $existing->main_image_url) {
                $payload['main_image_url'] = $existing->main_image_url;
            }
            if (empty($payload['images']) && ! empty($existing->images)) {
                $payload['images'] = $existing->images;
            }
            // Preserve existing price when re-scrape fails to parse a new one
            if (($payload['price'] ?? 0) <= 0 && ($existing->price ?? 0) > 0) {
                $payload['price'] = $existing->price;
            }
        } elseif (($payload['price'] ?? 0) <= 0) {
            Log::warning('catalog.scrape_zero_price', [
                'marketplace' => $this->getMarketplace(),
                'name' => $payload['name'] ?? '',
                'url' => $externalUrl,
            ]);
        }

        $product = ScrapedProduct::updateOrCreate(
            [
                'source_marketplace' => $this->getMarketplace(),
                'external_url_hash' => $urlHash,
            ],
            $payload
        );

        if (ExcludedScrapedProduct::isCatalogHidden($name, $data['name_en'] ?? null, $category, $categoryEn)) {
            $this->stripFromQdrant($product);
            if ($product->product_family !== null || $product->product_subtype !== null || $product->embedded_at !== null) {
                $product->update([
                    'product_family' => null,
                    'product_subtype' => null,
                    'embedded_at' => null,
                ]);
            }
        }

        $scrapeUrl->update(['last_verified_at' => now()]);

        return $product;
    }

    protected function purgeProductAtUrl(string $externalUrl): void
    {
        $urlHash = hash('sha256', $externalUrl);

        $existing = ScrapedProduct::where('source_marketplace', $this->getMarketplace())
            ->where('external_url_hash', $urlHash)
            ->first();

        if ($existing === null) {
            return;
        }

        $this->stripFromQdrant($existing);
        $existing->delete();
    }

    protected function stripFromQdrant(ScrapedProduct $product): void
    {
        if ($product->embedded_at === null) {
            return;
        }

        try {
            app(QdrantCatalogClient::class)->deleteProduct($product->id);
        } catch (\Throwable $e) {
            Log::warning('catalog.scrape_qdrant_delete_failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function hasMeaningfulChanges(ScrapedProduct $existing, array $new): bool
    {
        $fields = ['name', 'name_en', 'description', 'category_en', 'product_family', 'product_subtype',
            'price', 'width_cm', 'depth_cm', 'height_cm'];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $new)) {
                continue;
            }
            if ((string) ($existing->$field ?? '') !== (string) ($new[$field] ?? '')) {
                return true;
            }
        }

        foreach (['material_tags', 'color_tags'] as $field) {
            if (! array_key_exists($field, $new)) {
                continue;
            }
            if (json_encode($existing->$field ?? []) !== json_encode($new[$field] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse dimension string into separate width/depth/height values.
     * Handles 3-part "WxDxH" (furniture) and 2-part "WxH" (tiles).
     * Supports European comma decimals (e.g. "50,2x50,2").
     */
    protected function parseDimensions(string $raw): array
    {
        $cleaned = preg_replace('/\s*(cm|mm|սմ|см)\s*/iu', '', $raw);
        $cleaned = str_replace(',', '.', $cleaned);
        $cleaned = preg_replace('/\s*x\s*/i', 'x', trim($cleaned));

        $parts = explode('x', $cleaned);

        if (count($parts) >= 3) {
            return [
                'width_cm' => $this->dimVal($parts[0]),
                'depth_cm' => $this->dimVal($parts[1]),
                'height_cm' => $this->dimVal($parts[2]),
                'has_dimensions' => true,
            ];
        }

        if (count($parts) === 2) {
            return [
                'width_cm' => $this->dimVal($parts[0]),
                'depth_cm' => $this->dimVal($parts[1]),
                'height_cm' => null,
                'has_dimensions' => true,
            ];
        }

        return [
            'width_cm' => null,
            'depth_cm' => null,
            'height_cm' => null,
            'has_dimensions' => false,
        ];
    }

    private function dimVal(string $v): ?int
    {
        $f = (float) trim($v);

        return $f > 0 ? (int) round($f) : null;
    }

    protected function log(string $message, ?Command $command = null, string $level = 'info'): void
    {
        if ($command) {
            $command->$level($message);
        }
        Log::channel('scraper')->$level("[{$this->getMarketplace()}] {$message}");
    }

    /**
     * Fetch a URL with guaranteed minimum delay between consecutive requests.
     * This is the ONLY method that should make HTTP requests to the marketplace.
     */
    protected function fetchHtml(string $url): string
    {
        $this->enforceRateLimit();
        $response = $this->http->get($url);
        $this->lastRequestAt = microtime(true);

        return (string) $response->getBody();
    }

    /**
     * Sleep if necessary to ensure at least $delaySeconds since the last request.
     */
    private function enforceRateLimit(): void
    {
        if ($this->lastRequestAt > 0) {
            $elapsed = microtime(true) - $this->lastRequestAt;
            $remaining = $this->delaySeconds - $elapsed;
            if ($remaining > 0) {
                usleep((int) ($remaining * 1_000_000));
            }
        }
    }
}
