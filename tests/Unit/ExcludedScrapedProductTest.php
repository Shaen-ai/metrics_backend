<?php

namespace Tests\Unit;

use App\Services\Catalog\ExcludedScrapedProduct;
use Tests\TestCase;

class ExcludedScrapedProductTest extends TestCase
{
    public function test_kitchen_towel_is_excluded(): void
    {
        $this->assertTrue(ExcludedScrapedProduct::isExcluded('Kitchen towel OTIS 50x70 grey', null, 'Homeware > Tea towels', null));
    }

    public function test_apron_set_is_excluded(): void
    {
        $this->assertTrue(ExcludedScrapedProduct::isExcluded('Apron set FALEZ blue', null, null, null));
    }

    public function test_bathrobe_is_excluded(): void
    {
        $this->assertTrue(ExcludedScrapedProduct::isExcluded('Bathrobe RESTFUL white', null, null, null));
    }

    public function test_blanket_is_excluded(): void
    {
        $this->assertTrue(ExcludedScrapedProduct::isExcluded('Blanket winter RESTFUL 150X210', null, 'Textile', null));
    }

    public function test_pillow_is_excluded(): void
    {
        $this->assertTrue(ExcludedScrapedProduct::isExcluded('Pillow KOOPMAN XMAS TREE 30X50CM', null, null, null));
    }

    public function test_bath_mat_is_excluded(): void
    {
        $this->assertTrue(ExcludedScrapedProduct::isExcluded('Bath mat KARLSTAD Ø70 light grey', null, 'Bathroom', null));
    }

    public function test_generic_lamp_is_excluded(): void
    {
        $this->assertTrue(ExcludedScrapedProduct::isExcluded('Lamp KENT beige', null, null, null));
    }

    public function test_floor_lamp_is_excluded(): void
    {
        $this->assertTrue(ExcludedScrapedProduct::isExcluded('Floor lamp KENT #30xH140cm beige', null, null, null));
    }

    public function test_armenian_lamp_category_is_excluded(): void
    {
        $this->assertTrue(ExcludedScrapedProduct::isExcluded('Լուսատուն KENT', null, 'Լուսատուներ', null));
    }

    public function test_chandelier_is_not_excluded(): void
    {
        $this->assertFalse(ExcludedScrapedProduct::isExcluded('Chandelier HAAKON Ø35cm rattan', null, 'Ջահեր', null));
    }

    public function test_table_lamp_is_not_excluded(): void
    {
        $this->assertFalse(ExcludedScrapedProduct::isExcluded('Table lamp KOOPMAN 23X34CM BLACK', null, null, null));
    }

    public function test_lampshade_is_not_excluded(): void
    {
        $this->assertFalse(ExcludedScrapedProduct::isExcluded('Lampshade OTIS white', null, 'Լուսամփոփներ', null));
    }

    public function test_battery_lamp_is_not_excluded(): void
    {
        $this->assertFalse(ExcludedScrapedProduct::isExcluded('Battery lamp LED portable', null, null, null));
    }

    public function test_towel_rail_is_catalog_hidden_not_excluded(): void
    {
        $this->assertFalse(ExcludedScrapedProduct::isExcluded('Towel rail KARLSTAD chrome', null, null, null));
        $this->assertTrue(ExcludedScrapedProduct::isCatalogHidden('Towel rail KARLSTAD chrome', null, null, null));
    }

    public function test_laminate_flooring_is_not_excluded(): void
    {
        $this->assertFalse(ExcludedScrapedProduct::isExcluded('Laminate OAK 8mm', null, 'Flooring', null));
        $this->assertFalse(ExcludedScrapedProduct::isCatalogHidden('Laminate OAK 8mm', null, 'Flooring', null));
    }

    public function test_mosaic_flooring_name_is_catalog_hidden(): void
    {
        $this->assertFalse(ExcludedScrapedProduct::isExcluded('Մոզաիկա 30x30 grey', null, 'Flooring', null));
        $this->assertTrue(ExcludedScrapedProduct::isCatalogHidden('Մոզաիկա 30x30 grey', null, 'Flooring', null));
    }

    public function test_carpet_name_is_catalog_hidden(): void
    {
        $this->assertFalse(ExcludedScrapedProduct::isExcluded('Carpet KARLSTAD 160x230', null, null, null));
        $this->assertTrue(ExcludedScrapedProduct::isCatalogHidden('Carpet KARLSTAD 160x230', null, null, null));
    }

    public function test_wallpaper_name_is_catalog_hidden(): void
    {
        $this->assertTrue(ExcludedScrapedProduct::isCatalogHidden('Wallpaper floral green', null, null, null));
    }

    public function test_wall_tile_name_is_catalog_hidden(): void
    {
        $this->assertTrue(ExcludedScrapedProduct::isCatalogHidden('Պատի սալիկ 20x30 white', null, null, null));
    }

    public function test_decorate_objects_koopman_is_catalog_hidden_not_excluded(): void
    {
        $this->assertFalse(ExcludedScrapedProduct::isExcluded('Decorate objects KOOPMAN XMAS', null, null, null));
        $this->assertTrue(ExcludedScrapedProduct::isCatalogHidden('Decorate objects KOOPMAN XMAS', null, null, null));
    }

    public function test_koopman_santa_on_is_hard_excluded(): void
    {
        $this->assertTrue(ExcludedScrapedProduct::isExcluded('N/y decorate objects KOOPMAN SANTA ON', null, null, null));
        $this->assertFalse(ExcludedScrapedProduct::isCatalogHidden('N/y decorate objects KOOPMAN SANTA ON', null, null, null));
    }
}
