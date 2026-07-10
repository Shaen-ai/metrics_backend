<?php

namespace Tests\Unit;

use App\Services\Scraper\VegaScraper;
use ReflectionMethod;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class VegaScraperPricesTest extends TestCase
{
    /**
     * @return array{price: int, old_price: int|null}
     */
    private function callExtractPrices(string $html): array
    {
        $scraper = new VegaScraper;
        $method = new ReflectionMethod($scraper, 'extractPrices');

        return $method->invoke($scraper, new Crawler($html), $html);
    }

    public function test_extracts_sale_and_old_price_from_data_attributes(): void
    {
        $html = <<<'HTML'
        <div class="product-center">
          <div class="price price-action-group"
               data-price-unit="529900" data-special-unit="467900">
            <span class="price-old product-old-price-main">529 900 ֏</span>
            <span class="price-new">467 900 ֏</span>
          </div>
        </div>
        HTML;

        $prices = $this->callExtractPrices($html);

        $this->assertSame(467900, $prices['price']);
        $this->assertSame(529900, $prices['old_price']);
    }

    public function test_does_not_concatenate_parent_price_container_text(): void
    {
        $html = <<<'HTML'
        <div class="product-center">
          <div class="price price-action-group">
            <input type="text" value="1" />
            <span class="price-old product-old-price-main">529 900 ֏</span>
            <span class="price-new">467 900 ֏</span>
            <span class="promo-date">10/07/26</span>
          </div>
        </div>
        HTML;

        $prices = $this->callExtractPrices($html);

        $this->assertSame(467900, $prices['price']);
        $this->assertSame(529900, $prices['old_price']);
        $this->assertNotSame(11529900, $prices['price']);
        $this->assertNotSame(529900467900, $prices['price']);
    }

    public function test_extracts_regular_price_without_discount(): void
    {
        $html = <<<'HTML'
        <div class="product-center">
          <div class="price" data-price-unit="125000">
            <span class="price-new">125 000 ֏</span>
          </div>
        </div>
        HTML;

        $prices = $this->callExtractPrices($html);

        $this->assertSame(125000, $prices['price']);
        $this->assertNull($prices['old_price']);
    }

    public function test_rejects_values_above_ceiling(): void
    {
        $html = <<<'HTML'
        <div class="product-center">
          <div class="price" data-price-unit="999999999">
            <span class="price-new">999 999 999 ֏</span>
          </div>
        </div>
        HTML;

        $prices = $this->callExtractPrices($html);

        $this->assertSame(0, $prices['price']);
    }

    public function test_falls_back_to_json_ld_price(): void
    {
        $html = <<<'HTML'
        <div class="product-center"><h1>Chair</h1></div>
        <script type="application/ld+json">
        {"@type":"Product","offers":{"@type":"Offer","price":467900}}
        </script>
        HTML;

        $prices = $this->callExtractPrices($html);

        $this->assertSame(467900, $prices['price']);
        $this->assertNull($prices['old_price']);
    }
}
