<?php

namespace App\Services\Scraper;

use Illuminate\Console\Command;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

class JyskScraper extends BaseScraper
{
    protected string $marketplace = 'jysk';

    private const BASE = 'https://jysk.am';

    private const MAX_LISTING_PAGES = 400;

    /**
     * Category listing URLs on jysk.am (English). Pagination: ?page=2 ...
     *
     * @var list<string>
     */
    private const CATEGORY_SEEDS = [
        'https://jysk.am/en/products/bathroom_2518/bath-mats_3014/',
        'https://jysk.am/en/products/bathroom_2518/shower-curtains_3013/',
        'https://jysk.am/en/products/bathroom_2518/bathroom-equipment_3015/',
        'https://jysk.am/en/products/bedroom_2517/bed-frames_3089/',
        'https://jysk.am/en/products/bedroom_2517/bed-throws_2858/',
        'https://jysk.am/en/products/bedroom_2517/pillows_3010/',
        'https://jysk.am/en/products/office_2519/office-chairs_3016/',
        'https://jysk.am/en/products/office_2519/desks-computer-desks_2779/',
        'https://jysk.am/en/products/office_2519/office-accessories_2785/',
        'https://jysk.am/en/products/living-room_2520/sofabeds_3017/',
        'https://jysk.am/en/products/living-room_2520/sofas_2769/',
        'https://jysk.am/en/products/living-room_2520/tv-units_2774/',
        'https://jysk.am/en/products/living-room_2520/armchairs_2771/',
        'https://jysk.am/en/products/living-room_2520/pouffes_3101/',
        'https://jysk.am/en/products/living-room_2520/coffee-end-tables_2773/',
        'https://jysk.am/en/products/dining-room_2521/dining-tables_3018/',
        'https://jysk.am/en/products/dining-room_2521/folding-chairs-stool_2796/',
        'https://jysk.am/en/products/dining-room_2521/sideboards-cabinets_3020/',
        'https://jysk.am/en/products/dining-room_2521/bar-furniture_2797/',
        'https://jysk.am/en/products/dining-room_2521/dining-chairs_2795/',
        'https://jysk.am/en/products/dining-room_2521/console-tables_2792/',
        'https://jysk.am/en/products/storage_2522/wardrobes_3021/',
        'https://jysk.am/en/products/storage_2522/chest-of-drawers_3091/',
        'https://jysk.am/en/products/storage_2522/shelves_3103/',
        'https://jysk.am/en/products/storage_2522/bkcases-room-divider_3022/',
        'https://jysk.am/en/products/storage_2522/shoe-storage_2789/',
        'https://jysk.am/en/products/storage_2522/hangers_3114/',
        'https://jysk.am/en/products/storage_2522/hallway-units_3023/',
        'https://jysk.am/en/products/storage_2522/small-furniture-misc_3099/',
        'https://jysk.am/en/products/storage_2522/baskets-etc_3040/',
        'https://jysk.am/en/products/storage_2522/clothes-rails_3102/',
        'https://jysk.am/en/products/curtains_2523/ready-made-curtains_3025/',
        'https://jysk.am/en/products/homeware_2525/decoration_3033/',
        'https://jysk.am/en/products/homeware_2525/throws_3037/',
        'https://jysk.am/en/products/homeware_2525/kids-products_3046/',
        'https://jysk.am/en/products/homeware_2525/tea-towels_2870/',
        'https://jysk.am/en/products/homeware_2525/table-linen_3038/',
        'https://jysk.am/en/products/homeware_2525/laundry_3035/',
        'https://jysk.am/en/products/homeware_2525/kitchen_3260/',
        'https://jysk.am/en/products/homeware_2525/mirrors_2791/',
        'https://jysk.am/en/products/homeware_2525/lighting_3036/',
        'https://jysk.am/en/products/homeware_2525/cushions_3045/',
        'https://jysk.am/en/products/homeware_2525/hides_2883/',
    ];

    public function getMarketplace(): string
    {
        return 'jysk';
    }

    /**
     * JYSK product URLs do not end in .html; avoid false “redirect to non-product” from BaseScraper.
     */
    protected function isRedirectToNonProduct(ResponseInterface $response, string $originalUrl): bool
    {
        $redirects = $response->getHeader('X-Guzzle-Redirect-History');
        if (! empty($redirects)) {
            $finalUrl = end($redirects);
            $finalPath = parse_url($finalUrl, PHP_URL_PATH) ?? '';
            if (str_contains($finalPath, '/en/product/')) {
                return false;
            }
        }

        return parent::isRedirectToNonProduct($response, $originalUrl);
    }

    public function discoverUrls(?Command $command = null, ?array $categoryFilter = null): int
    {
        $totalInserted = 0;

        foreach (self::CATEGORY_SEEDS as $listingUrl) {
            $normalized = $this->normalizeListingUrl($listingUrl);
            $slug = basename(rtrim(parse_url($normalized, PHP_URL_PATH) ?? '/', '/'));
            $this->log("--- Listing: {$slug} ---", $command);

            $totalInserted += $this->discoverFromPagedListing($normalized, $command);
        }

        $this->log("=== Discovery complete: {$totalInserted} new URLs inserted ===", $command);

        return $totalInserted;
    }

    public function discoverUrlsFromSearch(string $query, int $maxPages, ?Command $command = null): int
    {
        $query = trim($query);
        if ($query === '' || $maxPages < 1) {
            return 0;
        }

        $inserted = 0;

        for ($page = 1; $page <= $maxPages; $page++) {
            $params = ['q' => $query];
            if ($page > 1) {
                $params['page'] = $page;
            }
            $pageUrl = self::BASE.'/en/search?'.http_build_query($params);
            $this->log("    JYSK search: {$pageUrl}", $command);

            try {
                $html = $this->fetchHtml($pageUrl);
            } catch (\Throwable $e) {
                $this->log("      ERROR: {$e->getMessage()}", $command, 'error');

                break;
            }

            $productUrls = $this->extractProductUrlsFromListingHtml($html);

            if (empty($productUrls)) {
                if ($page === 1) {
                    $this->log('      No products on search page 1', $command);
                }

                break;
            }

            $pageInserted = 0;
            foreach ($productUrls as $url) {
                $externalId = basename(rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/'));
                if ($this->recordDiscoveredUrl($url, $externalId, 'search')) {
                    $pageInserted++;
                }
            }

            $inserted += $pageInserted;
            $this->log('      Page '.$page.': '.count($productUrls).' products, '.$pageInserted.' new', $command);

            if ($page >= $maxPages) {
                break;
            }
        }

        return $inserted;
    }

    private function normalizeListingUrl(string $url): string
    {
        $url = trim($url);
        if (! str_starts_with($url, 'http')) {
            $url = self::BASE.'/'.ltrim($url, '/');
        }

        return rtrim($url, '/').'/';
    }

    private function discoverFromPagedListing(string $normalizedListingUrl, ?Command $command): int
    {
        $inserted = 0;
        $page = 1;

        while (true) {
            $pageUrl = $this->listingUrlWithPage($normalizedListingUrl, $page);
            $this->log("    Fetching: {$pageUrl}", $command);

            try {
                $html = $this->fetchHtml($pageUrl);
            } catch (\Throwable $e) {
                $this->log("      ERROR: {$e->getMessage()}", $command, 'error');

                break;
            }

            $productUrls = $this->extractProductUrlsFromListingHtml($html);

            if (empty($productUrls)) {
                if ($page === 1) {
                    $this->log('      No products on page 1', $command);
                }

                break;
            }

            $pageInserted = 0;
            foreach ($productUrls as $url) {
                $externalId = basename(rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/'));
                if ($this->recordDiscoveredUrl($url, $externalId)) {
                    $pageInserted++;
                }
            }

            $inserted += $pageInserted;
            $this->log("      Page {$page}: ".count($productUrls).' products, '.$pageInserted.' new', $command);

            if ($page >= self::MAX_LISTING_PAGES) {
                break;
            }

            $page++;
        }

        return $inserted;
    }

    private function listingUrlWithPage(string $normalizedListingUrl, int $page): string
    {
        if ($page <= 1) {
            return $normalizedListingUrl;
        }

        $separator = str_contains($normalizedListingUrl, '?') ? '&' : '?';

        return $normalizedListingUrl.$separator.'page='.$page;
    }

    /**
     * @return list<string>
     */
    private function extractProductUrlsFromListingHtml(string $html): array
    {
        if (! preg_match_all('#/en/product/[^"\'\s<>]+#', $html, $matches)) {
            return [];
        }

        $urls = [];

        foreach ($matches[0] as $rawPath) {
            $path = trim($rawPath, '/');
            if ($path === '' || ! str_starts_with($path, 'en/product/')) {
                continue;
            }
            $full = self::BASE.'/'.$path.'/';
            $urls[$full] = $full;
        }

        return array_values($urls);
    }

    protected function parseProductPage(string $html, string $url): ?array
    {
        $crawler = new Crawler($html);

        try {
            $name = null;
            $h1 = $crawler->filter('h1.title');
            if ($h1->count()) {
                $name = trim($h1->first()->text(''));
            }
            if ($name === null || $name === '') {
                $h1b = $crawler->filter('h1');
                if ($h1b->count()) {
                    $name = trim($h1b->first()->text(''));
                }
            }
            if ($name === null || $name === '') {
                return null;
            }

            $price = 0;
            $oldPrice = null;

            $priceWrap = $crawler->filter('.price');
            if ($priceWrap->count()) {
                $currentEl = $priceWrap->first()->filter('.current');
                if ($currentEl->count()) {
                    $price = $this->parseAmdDigits($currentEl->first()->text(''));
                }
                $oldEl = $priceWrap->first()->filter('.old');
                if ($oldEl->count()) {
                    $parsedOld = $this->parseAmdDigits($oldEl->first()->text(''));
                    if ($parsedOld > 0 && $parsedOld > $price) {
                        $oldPrice = $parsedOld;
                    }
                }
            }

            $images = $this->extractProductImages($crawler);

            if ($price === 0 && empty($images)) {
                return null;
            }

            $breadcrumbText = null;
            try {
                $crumbs = [];
                $crawler->filter('ol.breadcrumb li.breadcrumb-item a')->each(function (Crawler $node) use (&$crumbs) {
                    $t = trim($node->text(''));
                    if ($t !== '' && strcasecmp($t, 'Home') !== 0) {
                        $crumbs[] = $t;
                    }
                });
                if ($crumbs !== []) {
                    $breadcrumbText = implode(' > ', $crumbs);
                }
            } catch (\Throwable) {
            }

            $path = parse_url($url, PHP_URL_PATH);
            $slug = $path !== null ? basename(trim($path, '/')) : '';

            $externalId = $slug !== '' ? (preg_match('/_(\d+)$/', $slug, $sid) ? $sid[1] : $slug) : null;

            return [
                'name' => $name,
                'name_en' => $name,
                'description' => null,
                'category' => $breadcrumbText,
                'category_en' => $breadcrumbText,
                'brand' => null,
                'price' => $price,
                'currency' => 'AMD',
                'old_price' => $oldPrice,
                'in_stock' => true,
                'width_cm' => null,
                'depth_cm' => null,
                'height_cm' => null,
                'dimensions_raw' => null,
                'has_dimensions' => false,
                'images' => $images,
                'main_image_url' => $images[0] ?? null,
                'attributes' => [],
                'sku' => null,
                'slug' => $slug,
                'external_id' => substr((string) $externalId, 0, 128),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseAmdDigits(string $text): int
    {
        return (int) preg_replace('/\D/', '', $text);
    }

    /**
     * @return list<string>
     */
    private function extractProductImages(Crawler $crawler): array
    {
        $urls = [];

        try {
            $crawler->filter('img[src*="jysk.am/media/products"]')->each(function (Crawler $img) use (&$urls) {
                $src = $img->attr('src');
                if ($src && str_starts_with($src, 'http')) {
                    $urls[$src] = $src;
                }
            });
        } catch (\Throwable) {
        }

        return array_values($urls);
    }
}
