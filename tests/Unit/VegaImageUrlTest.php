<?php

namespace Tests\Unit;

use App\Support\VegaImageUrl;
use Tests\TestCase;

class VegaImageUrlTest extends TestCase
{
    /** @dataProvider normalizeProvider */
    public function test_normalize(string $label, ?string $input, ?string $expected): void
    {
        $this->assertSame($expected, VegaImageUrl::normalize($input), $label);
    }

    public static function normalizeProvider(): array
    {
        return [
            ['null passthrough', null, null],
            ['empty passthrough', '', ''],
            [
                'cache 2000x1500 jpg',
                'https://vega.am/image/cache/catalog/Angel/entrance-set/HOBEL%20SHL%2060_K086%200164_(4)%20interior-2000x1500.jpg',
                'https://vega.am/image/catalog/Angel/entrance-set/HOBEL%20SHL%2060_K086%200164_(4)%20interior.jpg',
            ],
            [
                'cache 1500x1500 jpg',
                'https://vega.am/image/cache/catalog/Angel/entrance-set/HOBEL%20SHL%2060_K086%200164_(4)%20interior-1500x1500.jpg',
                'https://vega.am/image/catalog/Angel/entrance-set/HOBEL%20SHL%2060_K086%200164_(4)%20interior.jpg',
            ],
            [
                'cache 120x120 webp',
                'https://vega.am/image/cache/webp/catalog/cat_img/audio-video/tv-120x120.webp',
                'https://vega.am/image/cache/webp/catalog/cat_img/audio-video/tv.webp',
            ],
            [
                'already canonical — no change',
                'https://vega.am/image/catalog/Angel/entrance-set/HOBEL%20SHL%2060.jpg',
                'https://vega.am/image/catalog/Angel/entrance-set/HOBEL%20SHL%2060.jpg',
            ],
            [
                'non-vega host — no change',
                'https://jysk.am/media/products/api/4912562_1.jpg',
                'https://jysk.am/media/products/api/4912562_1.jpg',
            ],
            [
                'bed linen cache url',
                'https://vega.am/image/cache/catalog/Angel/bed%20linen%20with%20blanket%20set/RV113V134%20BS-2000x1500.jpg',
                'https://vega.am/image/catalog/Angel/bed%20linen%20with%20blanket%20set/RV113V134%20BS.jpg',
            ],
            [
                'png extension',
                'https://vega.am/image/cache/catalog/Angel/test-product-500x500.png',
                'https://vega.am/image/catalog/Angel/test-product.png',
            ],
        ];
    }

    public function test_normalize_array_deduplicates(): void
    {
        $input = [
            'https://vega.am/image/cache/catalog/Angel/foo-2000x1500.jpg',
            'https://vega.am/image/cache/catalog/Angel/foo-1500x1500.jpg',
            'https://vega.am/image/cache/catalog/Angel/bar-2000x1500.jpg',
        ];

        $result = VegaImageUrl::normalizeArray($input);

        $this->assertSame([
            'https://vega.am/image/catalog/Angel/foo.jpg',
            'https://vega.am/image/catalog/Angel/bar.jpg',
        ], $result);
    }

    public function test_normalize_array_null_passthrough(): void
    {
        $this->assertNull(VegaImageUrl::normalizeArray(null));
    }

    public function test_normalize_array_preserves_non_vega(): void
    {
        $input = [
            'https://jysk.am/media/img1.jpg',
            'https://vega.am/image/cache/catalog/Angel/foo-2000x1500.jpg',
        ];

        $result = VegaImageUrl::normalizeArray($input);

        $this->assertSame([
            'https://jysk.am/media/img1.jpg',
            'https://vega.am/image/catalog/Angel/foo.jpg',
        ], $result);
    }
}
