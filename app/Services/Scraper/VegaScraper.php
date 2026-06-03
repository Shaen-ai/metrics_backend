<?php

namespace App\Services\Scraper;

use App\Support\VegaImageUrl;
use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;

class VegaScraper extends BaseScraper
{
    protected string $marketplace = 'vega';

    private const BASE_EN = 'https://vega.am/en/';

    /**
     * Only scrape products from these categories and their subcategories.
     * These are the home/furniture/interior-relevant sections of Vega.
     */
    private const ALLOWED_CATEGORIES = [
        'large-home-appliances',
        'smart-home-2',
        'plumbing',
        'doors-windows',
        'living-room-furniture',
        'lighting-decors',
        'bedroom-furniture',
        'textile',
        'kitchen-furniture',
        'tableware',
        'hallway-furniture',
        'office-furniture',
        'audio-video',
        'wallpapers',
    ];

    /**
     * Additional Vega listing URLs (e.g. manufacturer/brand pages as .html routes).
     * Not covered by category slug discovery under {@see self::ALLOWED_CATEGORIES}.
     */
    private const EXTRA_LISTING_URLS = [
        'https://vega.am/en/hobel-2.html',
        'https://vega.am/en/living-room-furniture/dining-sets/',
        'https://vega.am/en/living-room-furniture/dining-tables/',
    ];

    public function getMarketplace(): string
    {
        return 'vega';
    }

    /**
     * Discover product URLs by crawling allowed category pages on Vega.am.
     *
     * Instead of the full sitemap (which includes 19K+ products from all categories
     * including cosmetics, toys, phones etc.), we only crawl the specific categories
     * relevant to interior design and home furnishing.
     *
     * For each category: fetch listing pages, extract subcategory links,
     * then paginate through each subcategory to collect product .html URLs.
     * Rate-limits at 10s between requests to respect Vega's bot policy.
     */
    public function discoverUrls(?Command $command = null, ?array $categoryFilter = null): int
    {
        $totalInserted = 0;

        foreach (self::ALLOWED_CATEGORIES as $categorySlug) {
            $categoryUrl = self::BASE_EN.$categorySlug.'/';
            $this->log("--- Category: {$categorySlug} ---", $command);

            try {
                $html = $this->fetchHtml($categoryUrl);
            } catch (\Throwable $e) {
                $this->log("  ERROR fetching category page: {$e->getMessage()}", $command, 'error');

                continue;
            }

            $crawler = new Crawler($html);

            // Extract subcategory links from the category page
            $subcategoryUrls = $this->extractSubcategoryLinks($crawler, $categorySlug);

            if (empty($subcategoryUrls)) {
                // No subcategories — the category page itself lists products
                $this->log('  No subcategories found, treating as direct listing', $command);
                $subcategoryUrls = [$categoryUrl];
            } else {
                $this->log('  Found '.count($subcategoryUrls).' subcategories', $command);
            }

            foreach ($subcategoryUrls as $subUrl) {
                $subName = basename(rtrim($subUrl, '/'));
                $inserted = $this->discoverFromListingPage($subUrl, $command);
                $totalInserted += $inserted;
            }
        }

        foreach (self::EXTRA_LISTING_URLS as $listingUrl) {
            $this->log('--- Extra listing: '.$listingUrl.' ---', $command);
            $totalInserted += $this->discoverFromListingPage($listingUrl, $command);
        }

        $this->log("=== Discovery complete: {$totalInserted} new URLs inserted ===", $command);

        return $totalInserted;
    }

    /**
     * Extract subcategory links from a Vega category page.
     * Subcategory tiles use: <a href="..."><span class="cat-title">Name</span></a>
     * URLs look like: /en/parent-category/sub-category/
     */
    private function extractSubcategoryLinks(Crawler $crawler, string $parentSlug): array
    {
        $links = [];

        try {
            $crawler->filter('span.cat-title')->each(function (Crawler $node) use (&$links) {
                $parent = $node->closest('a');
                if (! $parent || ! $parent->count()) {
                    return;
                }

                $href = $parent->attr('href');
                if (! $href) {
                    return;
                }

                if (! str_starts_with($href, 'http')) {
                    $href = 'https://vega.am'.$href;
                }

                if (str_contains($href, 'vega.am/en/')) {
                    $links[] = rtrim($href, '/').'/';
                }
            });
        } catch (\Throwable) {
        }

        return array_values(array_unique($links));
    }

    /**
     * Paginate through a category/subcategory listing page and collect product URLs.
     * Vega pagination: /en/category/sub/page-N?limit=100
     */
    private function discoverFromListingPage(string $listingUrl, ?Command $command): int
    {
        $inserted = 0;
        $page = 1;
        $listingBase = rtrim($listingUrl, '/');

        while (true) {
            $pageUrl = $page === 1
                ? (str_ends_with($listingBase, '.html')
                    ? $listingBase.'?limit=100'
                    : $listingBase.'/?limit=100')
                : $listingBase.'/page-'.$page.'?limit=100';

            $this->log("  Fetching: {$pageUrl}", $command);

            try {
                $html = $this->fetchHtml($pageUrl);
            } catch (\Throwable $e) {
                $this->log("    ERROR: {$e->getMessage()}", $command, 'error');
                break;
            }

            $productUrls = $this->extractProductLinksFromListing($html);

            if (empty($productUrls)) {
                if ($page === 1) {
                    $this->log('    No products found on page 1', $command);
                }
                break;
            }

            $pageInserted = 0;
            foreach ($productUrls as $enUrl) {
                $externalId = basename(parse_url($enUrl, PHP_URL_PATH), '.html');
                if ($this->recordDiscoveredUrl($enUrl, $externalId)) {
                    $pageInserted++;
                }
            }

            $inserted += $pageInserted;
            $this->log("    Page {$page}: ".count($productUrls)." products, {$pageInserted} new", $command);

            if (count($productUrls) < 90 || ! str_contains($html, 'page-'.($page + 1))) {
                break;
            }

            $page++;
        }

        return $inserted;
    }

    /**
     * Discover product URLs from Vega English search listing (/en/search-en/).
     * Pagination matches category listings: page 2+ uses /page-N?search=&limit=.
     */
    public function discoverUrlsFromSearch(string $query, int $maxPages, ?Command $command = null): int
    {
        $query = trim($query);
        if ($query === '' || $maxPages < 1) {
            return 0;
        }

        $inserted = 0;
        $queryArgs = ['search' => $query, 'limit' => 100];

        for ($page = 1; $page <= $maxPages; $page++) {
            $qs = http_build_query($queryArgs);
            $pageUrl = $page === 1
                ? 'https://vega.am/en/search-en/?'.$qs
                : 'https://vega.am/en/search-en/page-'.$page.'?'.$qs;

            $this->log("  Search listing: {$pageUrl}", $command);

            try {
                $html = $this->fetchHtml($pageUrl);
            } catch (\Throwable $e) {
                $this->log("    ERROR: {$e->getMessage()}", $command, 'error');
                break;
            }

            $productUrls = $this->extractProductLinksFromListing($html);

            if (empty($productUrls)) {
                if ($page === 1) {
                    $this->log('    No products found on search page 1', $command);
                }
                break;
            }

            $pageInserted = 0;
            foreach ($productUrls as $enUrl) {
                $externalId = basename(parse_url($enUrl, PHP_URL_PATH), '.html');
                if ($this->recordDiscoveredUrl($enUrl, $externalId, 'search')) {
                    $pageInserted++;
                }
            }

            $inserted += $pageInserted;
            $this->log('    Search page '.$page.': '.count($productUrls).' products, '.$pageInserted.' new', $command);

            if ($page >= $maxPages) {
                break;
            }

            if (count($productUrls) < 90 || ! str_contains($html, 'page-'.($page + 1))) {
                break;
            }
        }

        return $inserted;
    }

    /**
     * Normalize Vega product links to English scrape URLs (/en/…html).
     */
    private function canonicalizeVegaListingProductHref(?string $href): ?string
    {
        if (! $href || ! str_ends_with($href, '.html')) {
            return null;
        }

        if (! str_starts_with($href, 'http')) {
            $href = 'https://vega.am'.$href;
        }

        $href = preg_replace('#^https?://(www\.)?vega\.am/am/#', 'https://vega.am/en/', $href) ?? $href;

        if (! str_contains($href, 'vega.am/en/')) {
            return null;
        }

        return $href;
    }

    /**
     * Extract product .html links from a Vega listing page.
     * Products are inside <div class="name"><a href="...html">
     */
    private function extractProductLinksFromListing(string $html): array
    {
        $urls = [];
        $crawler = new Crawler($html);

        try {
            $crawler->filter('.name a')->each(function (Crawler $node) use (&$urls) {
                $href = $node->attr('href');
                $canonical = $this->canonicalizeVegaListingProductHref($href);
                if ($canonical !== null) {
                    $urls[$canonical] = $canonical;
                }
            });
        } catch (\Throwable) {
        }

        return array_values($urls);
    }

    protected function parseProductPage(string $html, string $url): ?array
    {
        $crawler = new Crawler($html);

        $name = $this->extractText($crawler, 'h1');
        if (! $name) {
            return null;
        }

        // Detect non-product pages: if there's no price element and no product image, skip
        $price = $this->extractPrice($crawler, $html);
        $images = $this->extractImages($crawler);

        if ($price === 0 && empty($images)) {
            return null; // Not a real product page (could be category, info page, etc.)
        }

        $oldPrice = $this->extractOldPrice($crawler);
        $inStock = $this->checkInStock($crawler);
        $category = $this->extractCategory($crawler);
        $brand = $this->extractBrand($crawler);
        $attributes = $this->extractAttributes($crawler);
        $sku = $this->extractSku($crawler);
        $dimensions = $this->extractDimensionsFromAttributes($attributes);
        $description = $this->extractDescription($crawler);

        $slug = basename(parse_url($url, PHP_URL_PATH), '.html');

        // Build the user-facing Armenian product URL from the English scrape URL
        $productUrl = str_replace('/en/', '/am/', $url);

        return [
            'name' => $name,
            'name_en' => $name,
            'description' => $description,
            'category' => $category,
            'category_en' => $category,
            'brand' => $brand,
            'price' => $price,
            'currency' => 'AMD',
            'old_price' => $oldPrice,
            'in_stock' => $inStock,
            'width_cm' => $dimensions['width_cm'],
            'depth_cm' => $dimensions['depth_cm'],
            'height_cm' => $dimensions['height_cm'],
            'dimensions_raw' => $dimensions['raw'] ?? null,
            'has_dimensions' => $dimensions['has_dimensions'],
            'images' => $images,
            'main_image_url' => $images[0] ?? null,
            'attributes' => $attributes,
            'sku' => $sku,
            'slug' => $slug,
            'product_url' => $productUrl,
        ];
    }

    private function extractText(Crawler $crawler, string $selector): ?string
    {
        try {
            $node = $crawler->filter($selector)->first();

            return $node->count() ? trim($node->text()) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractPrice(Crawler $crawler, string $html = ''): int
    {
        try {
            $node = $crawler->filter('.product-center .price .price-new #price-special');
            if ($node->count()) {
                $parsed = $this->parseAmdPrice($node->text());
                if ($parsed > 0) {
                    return $parsed;
                }
            }

            $node = $crawler->filter('.product-center .price #price-new');
            if ($node->count()) {
                $parsed = $this->parseAmdPrice($node->text());
                if ($parsed > 0) {
                    return $parsed;
                }
            }

            $node = $crawler->filter('.product-center .price');
            if ($node->count()) {
                $parsed = $this->parseAmdPrice($node->text());
                if ($parsed > 0) {
                    return $parsed;
                }
            }

            $node = $crawler->filter('[class*="price"]');
            if ($node->count()) {
                foreach ($node as $element) {
                    $parsed = $this->parseAmdPrice((new Crawler($element))->text());
                    if ($parsed >= 1000) {
                        return $parsed;
                    }
                }
            }
        } catch (\Throwable) {
        }

        if ($html !== '') {
            return $this->extractPriceFromHtml($html);
        }

        return 0;
    }

    private function extractPriceFromHtml(string $html): int
    {
        if (preg_match('/<meta[^>]+itemprop=["\']price["\'][^>]+content=["\'](\d+)["\']/i', $html, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/<meta[^>]+content=["\'](\d+)["\'][^>]+itemprop=["\']price["\']/i', $html, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/<meta[^>]+property=["\']og:price:amount["\'][^>]+content=["\'](\d+)["\']/i', $html, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/<meta[^>]+content=["\'](\d+)["\'][^>]+property=["\']og:price:amount["\']/i', $html, $m)) {
            return (int) $m[1];
        }

        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $blocks)) {
            foreach ($blocks[1] as $jsonBlock) {
                $decoded = json_decode(trim($jsonBlock), true);
                if (! is_array($decoded)) {
                    continue;
                }
                $price = $this->extractPriceFromStructuredData($decoded);
                if ($price > 0) {
                    return $price;
                }
            }
        }

        if (preg_match('/"price"\s*:\s*"(\d{3,})"/', $html, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/"price"\s*:\s*(\d{3,})/', $html, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    private function extractPriceFromStructuredData(array $data): int
    {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $node) {
                if (is_array($node)) {
                    $price = $this->extractPriceFromStructuredData($node);
                    if ($price > 0) {
                        return $price;
                    }
                }
            }
        }

        if (isset($data['offers'])) {
            $offers = $data['offers'];
            if (is_array($offers) && isset($offers[0])) {
                foreach ($offers as $offer) {
                    if (is_array($offer)) {
                        $price = $this->extractPriceFromStructuredData($offer);
                        if ($price > 0) {
                            return $price;
                        }
                    }
                }
            } elseif (is_array($offers)) {
                $price = $this->extractPriceFromStructuredData($offers);
                if ($price > 0) {
                    return $price;
                }
            }
        }

        if (isset($data['price'])) {
            $raw = $data['price'];
            if (is_numeric($raw)) {
                return (int) $raw;
            }
            if (is_string($raw) && preg_match('/(\d+)/', $raw, $m)) {
                return (int) $m[1];
            }
        }

        if (isset($data['lowPrice']) && is_numeric($data['lowPrice'])) {
            return (int) $data['lowPrice'];
        }

        return 0;
    }

    private function extractOldPrice(Crawler $crawler): ?int
    {
        try {
            $node = $crawler->filter('.product-center .price .price-old-prin');
            if ($node->count()) {
                $val = $this->parseAmdPrice($node->text());

                return $val > 0 ? $val : null;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function parseAmdPrice(string $text): int
    {
        $cleaned = preg_replace('/[^\d]/', '', $text);

        return (int) $cleaned;
    }

    private function checkInStock(Crawler $crawler): bool
    {
        try {
            $node = $crawler->filter('#blink7');
            if ($node->count()) {
                return stripos($node->text(), 'In Stock') !== false;
            }
        } catch (\Throwable) {
        }

        return true;
    }

    private function extractImages(Crawler $crawler): array
    {
        $seen = [];
        $images = [];

        $push = function (?string $url) use (&$seen, &$images): void {
            if (! $url || ! str_contains($url, 'vega.am/image/')) {
                return;
            }
            if (! str_starts_with($url, 'http')) {
                $url = 'https://vega.am'.$url;
            }
            $normalized = VegaImageUrl::normalize($url) ?? $url;
            if (! isset($seen[$normalized])) {
                $seen[$normalized] = true;
                $images[] = $normalized;
            }
        };

        // New layout: gallery thumbnails with data-src
        try {
            $crawler->filter('.thumbnails img[data-src]')->each(function (Crawler $node) use ($push) {
                $push($node->attr('data-src'));
            });
        } catch (\Throwable) {
        }

        // Thumbnail anchor hrefs (old and new layouts)
        try {
            $crawler->filter('.thumbnails a[href*="vega.am/image/"]')->each(function (Crawler $node) use ($push) {
                $push($node->attr('href'));
            });
        } catch (\Throwable) {
        }

        // Legacy: .product-image-left thumbnails
        try {
            $crawler->filter('.product-image-left .thumbnails li a')->each(function (Crawler $node) use ($push) {
                $push($node->attr('href'));
            });
        } catch (\Throwable) {
        }

        // Any img with vega.am image src inside product gallery area
        if (empty($images)) {
            try {
                $crawler->filter('#image-box img, .product-image img')->each(function (Crawler $node) use ($push) {
                    $push($node->attr('data-src') ?? $node->attr('src'));
                });
            } catch (\Throwable) {
            }
        }

        // Fallback: main product image
        if (empty($images)) {
            try {
                $mainImage = $crawler->filter('#image');
                if ($mainImage->count()) {
                    $push($mainImage->attr('src'));
                }
            } catch (\Throwable) {
            }
        }

        return $images;
    }

    private function extractCategory(Crawler $crawler): ?string
    {
        try {
            $breadcrumbs = [];
            $crawler->filter('.breadcrumb li a')->each(function (Crawler $node) use (&$breadcrumbs) {
                $breadcrumbs[] = trim($node->text());
            });

            // Remove "Home" and last item (product name), return the category chain
            array_shift($breadcrumbs);
            if (count($breadcrumbs) > 0) {
                array_pop($breadcrumbs); // Remove product name from breadcrumb
            }

            return implode(' > ', $breadcrumbs) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractBrand(Crawler $crawler): ?string
    {
        try {
            $node = $crawler->filter('.brand-label img');
            if ($node->count()) {
                return trim($node->attr('alt') ?? $node->attr('title') ?? '');
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function extractAttributes(Crawler $crawler): array
    {
        $attrs = [];

        try {
            $crawler->filter('table.attribute tbody tr')->each(function (Crawler $row) use (&$attrs) {
                $cells = $row->filter('td');
                if ($cells->count() >= 2) {
                    $key = trim($cells->eq(0)->text());
                    $value = trim($cells->eq(1)->text());
                    $attrs[$key] = $value;
                }
            });
        } catch (\Throwable) {
        }

        return $attrs;
    }

    private function extractSku(Crawler $crawler): ?string
    {
        try {
            $node = $crawler->filter('.description .right');
            if ($node->count()) {
                $text = $node->text();
                $lines = preg_split('/\n|<br\s*\/?>/', $text);
                if (! empty($lines[0])) {
                    return trim($lines[0]);
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function extractDimensionsFromAttributes(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if (preg_match('/dimension|WxDxH|size|габарит|չափ/iu', $key)) {
                $dims = $this->parseDimensions($value);
                $dims['raw'] = $value;

                return $dims;
            }
        }

        return [
            'width_cm' => null,
            'depth_cm' => null,
            'height_cm' => null,
            'has_dimensions' => false,
            'raw' => null,
        ];
    }

    private function extractDescription(Crawler $crawler): ?string
    {
        try {
            $node = $crawler->filter('#tab-description');
            if ($node->count()) {
                $text = trim(strip_tags($node->html()));

                return $text ?: null;
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
