<?php

namespace App\Services\Scraper;

use Illuminate\Console\Command;

class DomusScraper extends BaseScraper
{
    protected string $marketplace = 'domus';

    private const BASE_URL = 'https://domus.am';

    /**
     * Only scrape products from these categories.
     * These are the home/furniture/interior-relevant sections of Domus.
     */
    private const ALLOWED_CATEGORIES = [
        'interior-doors',
        'exterior-doors',
        'chandelier',
        'light-fixture',
        'track-lighting',
        'spotlights',
        'led-panel-lights',
        'desk-lamps',
        'wallpapers',
        'locksmith-vices-1',
        'khvoshvor-kencaghayin-tekhnika',
        'kahvouyq',
        'hervoustacvouycner',
        'painting-tools',
        'tablechair-sets',
        'shelving-unit',
        'living-room-furniture',
        'gvorger',
        '000001099',
        'laminate-flooring',
        'wood-flooring',
        'tables',
        'floor-tiles',
        'erespatman-salikner',
        'porcelain-stonewares',
        'decorative-tiles',
        'ceramic-mosaics',
    ];

    /**
     * Armenian category → English translation for scraped Domus products.
     */
    private const CATEGORY_EN_MAP = [
        'Լամինատե Հատակներ' => 'Laminate Flooring',
        'Փայտե Հատակներ' => 'Wooden Flooring',
        'Փայտե Աթոռներ' => 'Wooden Chairs',
        '.Բազկաթոռներ' => 'Armchairs',
        'Գրասենյակային Աթոռներ' => 'Office Chairs',
        'Մետաղական Ոտքերով Աթոռներ' => 'Metal Leg Chairs',
        'Պլաստմասե Աթոռներ' => 'Plastic Chairs',
        'Բարային Աթոռներ' => 'Bar Stools',
        'Պուֆ Աթոռներ' => 'Pouf Chairs',
        'Ճոճաթոռներ' => 'Rocking Chairs',
        'Սեղաններ' => 'Tables',
        'Սեղան/Աթոռ Հավաքածուներ' => 'Table/Chair Sets',
        'Փափուկ Կահույք' => 'Upholstered Furniture',
        'Ննջասենյակի Կահույք' => 'Bedroom Furniture',
        'Հյուրասենյակի Կահույք' => 'Living Room Furniture',
        'Դարակաշարեր' => 'Shelving Units',
        'Մահճակալներ' => 'Beds',
        'Հիմքեր' => 'Bed Bases',
        'Ջահեր' => 'Chandeliers',
        'Լուսամփոփներ' => 'Light Fixtures',
        'Սեղանի Լուսատուներ' => 'Table Lamps',
        'Կետային Լուսատուներ' => 'Spotlights',
        'Ճաղաշարքային Համակարգ' => 'Track Lighting System',
        'Պաստառներ' => 'Wallpapers',
        'Ամառանոցային Աթոռներ' => 'Outdoor Chairs',
        'Ամառանոցային Կահույքի Հավաքածուներ' => 'Outdoor Furniture Sets',
        'Կախիչներ' => 'Coat Racks',
        'Դեկորատիվ Բարձեր' => 'Decorative Pillows',
        'Էլեկտրական Բուխարիներ' => 'Electric Fireplaces',
        'Գինու Սառնարաններ' => 'Wine Coolers',
        'Միկրոալիքային Վառարաններ' => 'Microwave Ovens',
        'Ներկառուցվող Իեռոցներ' => 'Built-in Ovens',
        'Ներկառուցվող Սալօջախներ և Գազօջախներ' => 'Built-in Cooktops and Gas Stoves',
        'Ներկառուցվող Սառնարաններ և Սառցարաններ' => 'Built-in Refrigerators and Freezers',
        'Ներկառուցվող Սրջեփներ' => 'Built-in Coffee Machines',
        'Սպասք Լվացող Մեքենաներ' => 'Dishwashers',
        'Տեխնիկայի Աքսեսուարներ' => 'Appliance Accessories',
        'Օդակլանիչներ' => 'Air Conditioners',
        'Հատակի Սալիկներ' => 'Floor Tiles',
        'Երեսպատման Սալիկներ' => 'Wall Tiles',
        'Կերամոգրանիտներ' => 'Porcelain Tiles',
        'Դեկորատիվ Սալիկներ' => 'Decorative Tiles',
        'Մոզաիկա Սալիկներ' => 'Ceramic Mosaics',
    ];

    public function getMarketplace(): string
    {
        return 'domus';
    }

    /**
     * Discover product URLs by crawling allowed category pages on Domus.am.
     *
     * All Domus categories list products directly with ?page=N pagination.
     * Product URLs appear in the Next.js RSC payload as /product/slug.
     */
    public function discoverUrls(?Command $command = null, ?array $categoryFilter = null): int
    {
        $totalInserted = 0;
        $slugs = $categoryFilter ?? self::ALLOWED_CATEGORIES;

        foreach ($slugs as $categorySlug) {
            if (! in_array($categorySlug, self::ALLOWED_CATEGORIES, true)) {
                $this->log("Skipping unknown category: {$categorySlug}", $command, 'warn');

                continue;
            }

            $categoryUrl = self::BASE_URL.'/category/'.$categorySlug;
            $this->log("--- Category: {$categorySlug} ---", $command);

            $inserted = $this->discoverFromDomusListing($categoryUrl, $command);
            $totalInserted += $inserted;
        }

        $this->log("=== Discovery complete: {$totalInserted} new URLs inserted ===", $command);

        return $totalInserted;
    }

    /**
     * Paginate through a Domus listing page and collect product URLs.
     * Domus uses ?page=N, 24 products per page, and embeds total pages as "pages":N in RSC.
     */
    private function discoverFromDomusListing(string $listingUrl, ?Command $command): int
    {
        $inserted = 0;
        $page = 1;
        $totalPages = null;

        while (true) {
            $pageUrl = $page === 1 ? $listingUrl : $listingUrl.'?page='.$page;
            $this->log("    Fetching: {$pageUrl}", $command);

            try {
                $html = $this->fetchHtml($pageUrl);
            } catch (\Throwable $e) {
                $this->log("      ERROR: {$e->getMessage()}", $command, 'error');
                break;
            }

            // On first page, detect total number of pages from RSC payload: pages\":38
            if ($page === 1 && preg_match('/pages["\\\\":]+(\d+)/', $html, $pagesMatch)) {
                $totalPages = (int) $pagesMatch[1];
                $this->log("      Total pages: {$totalPages}", $command);
            }

            $productUrls = $this->extractDomusProductLinks($html);

            if (empty($productUrls)) {
                if ($page === 1) {
                    $this->log('      No products found on page 1', $command);
                }
                break;
            }

            $pageInserted = 0;
            foreach ($productUrls as $url) {
                $externalId = basename(parse_url($url, PHP_URL_PATH));
                if ($this->recordDiscoveredUrl($url, $externalId)) {
                    $pageInserted++;
                }
            }

            $inserted += $pageInserted;
            $this->log("      Page {$page}: ".count($productUrls)." products, {$pageInserted} new", $command);

            if ($totalPages !== null && $page >= $totalPages) {
                break;
            }

            if ($totalPages === null && count($productUrls) < 20) {
                break;
            }

            $page++;
        }

        return $inserted;
    }

    /**
     * Discover product URLs from Domus search (/search?q=&limit=48&sort=new).
     */
    public function discoverUrlsFromSearch(string $query, int $maxPages, ?Command $command = null): int
    {
        $query = trim($query);
        if ($query === '' || $maxPages < 1) {
            return 0;
        }

        $inserted = 0;
        $totalPages = null;

        for ($page = 1; $page <= $maxPages; $page++) {
            $params = [
                'q' => $query,
                'limit' => 48,
                'sort' => 'new',
            ];
            if ($page > 1) {
                $params['page'] = $page;
            }

            $pageUrl = self::BASE_URL.'/search?'.http_build_query($params);
            $this->log("    Search listing: {$pageUrl}", $command);

            try {
                $html = $this->fetchHtml($pageUrl);
            } catch (\Throwable $e) {
                $this->log("      ERROR: {$e->getMessage()}", $command, 'error');
                break;
            }

            if ($page === 1 && preg_match('/pages["\\\\":]+(\d+)/', $html, $pagesMatch)) {
                $totalPages = (int) $pagesMatch[1];
            }

            $productUrls = $this->extractDomusProductLinks($html);

            if (empty($productUrls)) {
                if ($page === 1) {
                    $this->log('      No products found on search page 1', $command);
                }
                break;
            }

            $pageInserted = 0;
            foreach ($productUrls as $url) {
                $externalId = basename(parse_url($url, PHP_URL_PATH));
                if ($this->recordDiscoveredUrl($url, $externalId, 'search')) {
                    $pageInserted++;
                }
            }

            $inserted += $pageInserted;
            $this->log('      Search page '.$page.': '.count($productUrls).' products, '.$pageInserted.' new', $command);

            if ($totalPages !== null && $page >= $totalPages) {
                break;
            }

            if ($page >= $maxPages) {
                break;
            }

            if (count($productUrls) < 20) {
                break;
            }
        }

        return $inserted;
    }

    /**
     * Extract product links from a Domus listing page HTML.
     * Domus is a Next.js app — product URLs appear in RSC payload as /product/slug
     * (both in escaped JSON and occasionally as standard hrefs).
     */
    private function extractDomusProductLinks(string $html): array
    {
        $urls = [];

        preg_match_all('/\/product\/([a-z0-9][a-z0-9\-]*)/', $html, $matches);

        foreach (array_unique($matches[1]) as $slug) {
            $url = self::BASE_URL.'/product/'.$slug;
            $urls[$url] = $url;
        }

        return array_values($urls);
    }

    /**
     * Parse Domus product page HTML.
     * Domus uses Next.js SSR: product data is embedded in RSC script payloads.
     */
    protected function parseProductPage(string $html, string $url): ?array
    {
        $data = $this->extractRscProductData($html);
        if (! $data) {
            return null;
        }

        $slug = $this->extractSlugFromUrl($url);
        $title = $data['title'] ?? null;
        if (! $title) {
            return null;
        }

        $price = $data['price'] ?? 0;
        $oldPrice = $data['oldPrice'] ?? null;
        $inStock = $data['inStock'] ?? true;
        $media = $data['media'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $sku = $data['sku'] ?? null;
        $brand = $data['brand'] ?? null;
        $category = $data['category'] ?? null;

        $images = $this->extractImagesFromMedia($media);
        $dimensions = $this->extractDimensionsFromDomusAttributes($attributes);

        return [
            'name' => $title,
            'name_en' => null, // Domus names are in Armenian; translation deferred
            'description' => null,
            'category' => $category,
            'category_en' => self::CATEGORY_EN_MAP[$category] ?? null,
            'brand' => $brand,
            'price' => (int) $price,
            'currency' => 'AMD',
            'old_price' => $oldPrice ? (int) $oldPrice : null,
            'in_stock' => $inStock,
            'width_cm' => $dimensions['width_cm'],
            'depth_cm' => $dimensions['depth_cm'],
            'height_cm' => $dimensions['height_cm'],
            'dimensions_raw' => $dimensions['raw'] ?? null,
            'has_dimensions' => $dimensions['has_dimensions'],
            'images' => $images,
            'main_image_url' => $images[0] ?? null,
            'attributes' => array_map(
                fn ($a) => ['title' => $a['title'] ?? '', 'value' => $a['value'] ?? ''],
                $attributes
            ),
            'sku' => $sku,
            'slug' => $slug,
        ];
    }

    /**
     * Extract structured product data from Next.js RSC script payload.
     * The HTML contains literal \" (backslash-quote) as JSON escaping in RSC payloads.
     * We use string search + bracket-counting for robust extraction.
     */
    private function extractRscProductData(string $html): ?array
    {
        $result = [];
        $EQ = chr(92).chr(34); // literal \"

        // JSON-LD structured data (standard JSON, not RSC-escaped)
        if (preg_match('/"sku"\s*:\s*"([^"]*)"/', $html, $skuMatch)) {
            $result['sku'] = $skuMatch[1];
        }
        if (preg_match('/"price"\s*:\s*"(\d+)"/', $html, $priceMatch)) {
            $result['jsonLdPrice'] = (int) $priceMatch[1];
        }
        if (preg_match('/"availability"\s*:\s*"([^"]*)"/', $html, $availMatch)) {
            $result['inStock'] = str_contains($availMatch[1], 'InStock');
        }

        // Extract media array: find \"media\":[ and balance brackets
        $mediaStr = $this->extractJsonArray($html, $EQ.'media'.$EQ.':[');
        if ($mediaStr) {
            $result['media'] = $this->parseMediaArray($mediaStr);
        }

        // Extract attributes array
        $attrsStr = $this->extractJsonArray($html, $EQ.'attributes'.$EQ.':[');
        if ($attrsStr) {
            $result['attributes'] = $this->parseAttributesArray($attrsStr);
        }

        // Title: find \"title\":\"...\", right before \"header\":true
        $headerPos = strpos($html, $EQ.'header'.$EQ.':true');
        if ($headerPos !== false) {
            $titleKey = $EQ.'title'.$EQ.':'.$EQ;
            $searchRange = substr($html, max(0, $headerPos - 500), 500);
            $titleStart = strrpos($searchRange, $titleKey);
            if ($titleStart !== false) {
                $valStart = $titleStart + strlen($titleKey);
                $valEnd = strpos($searchRange, $EQ, $valStart);
                if ($valEnd !== false) {
                    $result['title'] = substr($searchRange, $valStart, $valEnd - $valStart);
                }
            }
        }

        // Price: \"price\":61900
        $priceKey = $EQ.'price'.$EQ.':';
        if (preg_match('/'.preg_quote($priceKey, '/').'(\d{3,})/', $html, $rscPrice)) {
            $result['price'] = (int) $rscPrice[1];
        }
        if (! isset($result['price']) && isset($result['jsonLdPrice'])) {
            $result['price'] = $result['jsonLdPrice'];
        }

        // Breadcrumbs
        $bcKey = $EQ.'breadcrumbs'.$EQ.':{'.$EQ.'items'.$EQ.':[';
        $bcContent = $this->extractJsonArray($html, $bcKey);
        if ($bcContent) {
            $items = $this->parseBreadcrumbItems($bcContent);
            if (count($items) > 0) {
                array_pop($items);
                $result['category'] = implode(' > ', $items) ?: null;
            }
        }

        if (preg_match('/"brand":\{"@type":"Brand","name":"([^"]*)"\}/', $html, $brandMatch)) {
            $result['brand'] = $brandMatch[1] ?: null;
        }

        if (empty($result['title'])) {
            if (preg_match('/<meta property="og:title" content="([^"]+)"/', $html, $ogMatch)) {
                $result['title'] = html_entity_decode($ogMatch[1]);
            }
        }

        return ! empty($result['title']) ? $result : null;
    }

    /**
     * Find a JSON array in the HTML by its key prefix, then balance [ ] brackets
     * to extract just the array content.
     */
    private function extractJsonArray(string $html, string $prefix): ?string
    {
        $pos = strpos($html, $prefix);
        if ($pos === false) {
            return null;
        }

        $start = $pos + strlen($prefix);
        $depth = 1;
        $len = strlen($html);

        for ($i = $start; $i < $len && $depth > 0; $i++) {
            if ($html[$i] === '[') {
                $depth++;
            } elseif ($html[$i] === ']') {
                $depth--;
            }
        }

        if ($depth !== 0) {
            return null;
        }

        return substr($html, $start, $i - $start - 1);
    }

    private function parseMediaArray(string $raw): array
    {
        $EQ = chr(92).chr(34);
        $media = [];
        $urlKey = $EQ.'url'.$EQ.':'.$EQ;

        $offset = 0;
        while (($pos = strpos($raw, $urlKey, $offset)) !== false) {
            $valStart = $pos + strlen($urlKey);
            $valEnd = strpos($raw, $EQ, $valStart);
            if ($valEnd === false) {
                break;
            }
            $url = substr($raw, $valStart, $valEnd - $valStart);
            if (! str_contains($url, 'thumbnail') && ! str_contains($url, '.webp')) {
                $media[] = ['url' => $url];
            }
            $offset = $valEnd + 1;
        }

        return $media;
    }

    private function parseAttributesArray(string $raw): array
    {
        $EQ = chr(92).chr(34);
        $attributes = [];

        $titleKey = $EQ.'title'.$EQ.':'.$EQ;
        $slugKey = $EQ.'slug'.$EQ.':'.$EQ;
        $valueKey = $EQ.'value'.$EQ.':'.$EQ;

        $offset = 0;
        while (($titlePos = strpos($raw, $titleKey, $offset)) !== false) {
            $titleValStart = $titlePos + strlen($titleKey);
            $titleValEnd = strpos($raw, $EQ, $titleValStart);
            if ($titleValEnd === false) {
                break;
            }
            $title = substr($raw, $titleValStart, $titleValEnd - $titleValStart);

            $slugPos = strpos($raw, $slugKey, $titleValEnd);
            if ($slugPos === false) {
                break;
            }
            $slugValStart = $slugPos + strlen($slugKey);
            $slugValEnd = strpos($raw, $EQ, $slugValStart);
            if ($slugValEnd === false) {
                break;
            }
            $slug = substr($raw, $slugValStart, $slugValEnd - $slugValStart);

            $valuePos = strpos($raw, $valueKey, $slugValEnd);
            if ($valuePos === false) {
                break;
            }
            $valueValStart = $valuePos + strlen($valueKey);
            $valueValEnd = strpos($raw, $EQ, $valueValStart);
            if ($valueValEnd === false) {
                break;
            }
            $value = substr($raw, $valueValStart, $valueValEnd - $valueValStart);

            $attributes[] = [
                'title' => $title,
                'slug' => $slug,
                'value' => $value,
            ];

            $offset = $valueValEnd + 1;
        }

        return $attributes;
    }

    private function parseBreadcrumbItems(string $raw): array
    {
        $EQ = chr(92).chr(34);
        $items = [];
        $titleKey = $EQ.'title'.$EQ.':'.$EQ;

        $offset = 0;
        while (($pos = strpos($raw, $titleKey, $offset)) !== false) {
            $valStart = $pos + strlen($titleKey);
            $valEnd = strpos($raw, $EQ, $valStart);
            if ($valEnd === false) {
                break;
            }
            $items[] = substr($raw, $valStart, $valEnd - $valStart);
            $offset = $valEnd + 1;
        }

        return $items;
    }

    private function extractImagesFromMedia(array $media): array
    {
        return array_values(array_map(fn ($m) => $m['url'], $media));
    }

    private function extractDimensionsFromDomusAttributes(array $attributes): array
    {
        foreach ($attributes as $attr) {
            $slug = $attr['slug'] ?? '';
            $title = $attr['title'] ?? '';
            if ($slug === 'size' || preg_match('/չափ|размер|dimension/iu', $title)) {
                $value = $attr['value'] ?? '';
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

    private function extractSlugFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return str_replace('/product/', '', $path);
    }

    private function unescapeRsc(string $str): string
    {
        return str_replace(['\\"', '\\/', '\\n', '\\\\'], ['"', '/', "\n", '\\'], $str);
    }
}
