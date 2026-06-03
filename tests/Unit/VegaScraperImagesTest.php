<?php

namespace Tests\Unit;

use App\Services\Scraper\VegaScraper;
use ReflectionMethod;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class VegaScraperImagesTest extends TestCase
{
    /**
     * Invoke the private extractImages() method via reflection.
     */
    private function callExtractImages(string $html): array
    {
        $scraper = new VegaScraper;
        $method = new ReflectionMethod($scraper, 'extractImages');

        return $method->invoke($scraper, new Crawler($html));
    }

    public function test_extracts_from_data_src_thumbnails(): void
    {
        $html = <<<'HTML'
        <div class="thumbnails sticky-left-block">
            <img data-src="https://vega.am/image/cache/catalog/Angel/entrance-set/HOBEL-1500x1500.jpg" />
            <img data-src="https://vega.am/image/cache/catalog/Angel/entrance-set/BAR-1500x1500.jpg" />
        </div>
        HTML;

        $images = $this->callExtractImages($html);

        $this->assertSame([
            'https://vega.am/image/catalog/Angel/entrance-set/HOBEL.jpg',
            'https://vega.am/image/catalog/Angel/entrance-set/BAR.jpg',
        ], $images);
    }

    public function test_deduplicates_across_sources(): void
    {
        $html = <<<'HTML'
        <div class="thumbnails">
            <img data-src="https://vega.am/image/cache/catalog/Angel/foo-1500x1500.jpg" />
            <a href="https://vega.am/image/cache/catalog/Angel/foo-2000x1500.jpg">link</a>
        </div>
        HTML;

        $images = $this->callExtractImages($html);

        $this->assertCount(1, $images);
        $this->assertSame('https://vega.am/image/catalog/Angel/foo.jpg', $images[0]);
    }

    public function test_legacy_product_image_left_still_works(): void
    {
        $html = <<<'HTML'
        <div class="product-image-left">
            <ul class="thumbnails">
                <li><a href="https://vega.am/image/cache/catalog/Angel/old-2000x1500.jpg">thumb</a></li>
            </ul>
        </div>
        HTML;

        $images = $this->callExtractImages($html);

        $this->assertSame([
            'https://vega.am/image/catalog/Angel/old.jpg',
        ], $images);
    }

    public function test_fallback_to_main_image(): void
    {
        $html = '<img id="image" src="https://vega.am/image/cache/catalog/Angel/main-600x600.jpg" />';

        $images = $this->callExtractImages($html);

        $this->assertSame([
            'https://vega.am/image/catalog/Angel/main.jpg',
        ], $images);
    }

    public function test_empty_html_returns_empty(): void
    {
        $this->assertSame([], $this->callExtractImages('<div>no product</div>'));
    }
}
