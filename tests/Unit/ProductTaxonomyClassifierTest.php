<?php

namespace Tests;

use App\Services\Catalog\ProductTaxonomyClassifier;

class ProductTaxonomyClassifierTest extends TestCase
{
    use CreatesApplication;

    private ProductTaxonomyClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new ProductTaxonomyClassifier;
    }

    public function test_sofa_in_ceiling_lights_category_classified_as_furniture_sofa(): void
    {
        $result = $this->classifier->classify('Sofa HOBEL DANIA 3 STR OAK LIGHT GREY', 'Ceiling lights', null);

        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('sofa', $result['product_subtype']);
    }

    public function test_sofa_with_lamp_in_category_still_furniture(): void
    {
        $result = $this->classifier->classify('Sofa HOBEL CORNER MODERN LUXE', 'Living room > Lamp', null);

        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('sofa', $result['product_subtype']);
    }

    public function test_bed_with_lighting_category_still_furniture(): void
    {
        $result = $this->classifier->classify('Bed LIGHT CAPPUCCINO 180x200', 'Bedroom > Lighting', null);

        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('bed', $result['product_subtype']);
    }

    public function test_pendant_in_ceiling_lights_category_stays_lighting(): void
    {
        $result = $this->classifier->classify('Pendant HAAKON Ø35cm rattan', 'Ceiling lights', null);

        $this->assertSame('lighting', $result['product_family']);
        $this->assertSame('pendant', $result['product_subtype']);
    }

    public function test_floor_lamp_is_excluded(): void
    {
        $result = $this->classifier->classify('Floor lamp KENT #30xH140cm beige', null, null);

        $this->assertNull($result['product_family']);
        $this->assertNull($result['product_subtype']);
    }

    public function test_curtain_in_wrong_category_still_window_treatments(): void
    {
        $result = $this->classifier->classify('Dimout curtain AMUNGEN 1x140x175 sand', 'Furniture > Storage', null);

        $this->assertSame('window_treatments', $result['product_family']);
        $this->assertSame('curtain', $result['product_subtype']);
    }

    public function test_curtain_light_is_lighting_not_window_treatments(): void
    {
        $result = $this->classifier->classify(
            'N/y decorate objects KOOPMAN CURTAIN LIGHT 120LED WW (232135) (AGB000540)',
            'Furniture & Interior > Lighting & decors',
            null,
        );

        $this->assertSame('lighting', $result['product_family']);
        $this->assertNotSame('curtain', $result['product_subtype']);
    }

    public function test_blanket_is_excluded(): void
    {
        $result = $this->classifier->classify(
            'Blanket winter RESTFUL PR19QV75V40 BW 150X210 BM LIGHT',
            'Furniture & Interior > Textile',
            null,
        );

        $this->assertNull($result['product_family']);
        $this->assertNull($result['product_subtype']);
    }

    public function test_plaid_is_excluded(): void
    {
        $result = $this->classifier->classify(
            'Plaid RESTFUL AC 170X200 LZ COFFEE',
            'Furniture & Interior > Textile',
            null,
        );

        $this->assertNull($result['product_family']);
        $this->assertNull($result['product_subtype']);
    }

    public function test_real_curtain_with_light_color_stays_window_treatments(): void
    {
        $result = $this->classifier->classify('Curtain ISTEREN 1x140x300 light brown', null, null);

        $this->assertSame('window_treatments', $result['product_family']);
        $this->assertSame('curtain', $result['product_subtype']);
    }

    public function test_pillow_is_excluded(): void
    {
        $result = $this->classifier->classify(
            'Pillow KOOPMAN XMAS TREE 30X50CM (AAE820060)',
            'Furniture & Interior > Textile',
            null,
        );

        $this->assertNull($result['product_family']);
        $this->assertNull($result['product_subtype']);
    }

    public function test_xmas_pillow_excluded_via_name_first(): void
    {
        $result = $this->classifier->classifyFromName('Pillow KOOPMAN XMAS TREE 30X50CM');

        $this->assertNull($result);
    }

    public function test_tv_stand_name_wins_over_lighting_category(): void
    {
        $result = $this->classifier->classify('Tv stand HOBEL EX-C34 VITO', 'Ceiling lights', null);

        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('tv_stand', $result['product_subtype']);
    }

    public function test_classify_from_name_returns_null_for_ambiguous(): void
    {
        $result = $this->classifier->classifyFromName('KOOPMAN TABLE LAMP 23X34CM BLACK');

        $this->assertNull($result);
    }

    public function test_classify_from_name_sofa(): void
    {
        $result = $this->classifier->classifyFromName('Sofa HOBEL CORNER WOOD GREY');

        $this->assertNotNull($result);
        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('sofa', $result['product_subtype']);
    }

    public function test_expected_classification_is_classify_alias(): void
    {
        $a = $this->classifier->classify('Sofa HOBEL', 'Decor', null);
        $b = $this->classifier->expectedClassification('Sofa HOBEL', 'Decor', null);

        $this->assertSame($a, $b);
    }

    public function test_material_and_color_tags_still_extracted(): void
    {
        $result = $this->classifier->classify('Sofa HOBEL OAK GREY VELVET', 'Ceiling lights', null);

        $this->assertSame('furniture', $result['product_family']);
        $this->assertContains('oak', $result['material_tags']);
        $this->assertContains('velvet', $result['material_tags']);
        $this->assertContains('grey', $result['color_tags']);
    }

    public function test_tealight_holder_classified_as_home_accessories(): void
    {
        $result = $this->classifier->classify('Tealight holder OTIS H8cm silver', 'Candles & Holders', null);

        $this->assertSame('home_accessories', $result['product_family']);
    }

    public function test_flashlight_classified_as_home_accessories(): void
    {
        $result = $this->classifier->classify('Flashlight ERA GA-502', 'Lighting', null);

        $this->assertSame('home_accessories', $result['product_family']);
    }

    public function test_dining_chair_with_light_color_classified_as_chair(): void
    {
        $result = $this->classifier->classify('Dining chair MELBY light brown fabric/black', 'Dining', null);

        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('chair', $result['product_subtype']);
    }

    public function test_folding_chair_classified_as_chair(): void
    {
        $result = $this->classifier->classify('Folding chair VOEL light sand', 'Furniture', null);

        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('chair', $result['product_subtype']);
    }

    public function test_bath_mat_is_excluded(): void
    {
        $result = $this->classifier->classify('Bath mat KARLSTAD Ø70 light grey', 'Bathroom', null);

        $this->assertNull($result['product_family']);
        $this->assertNull($result['product_subtype']);
    }

    public function test_table_lamp_stays_lighting(): void
    {
        $result = $this->classifier->classify('Table lamp KOOPMAN 23X34CM BLACK', null, null);

        $this->assertSame('lighting', $result['product_family']);
        $this->assertSame('table', $result['product_subtype']);
    }

    public function test_towel_rail_has_null_family(): void
    {
        $result = $this->classifier->classify('Towel rail KARLSTAD chrome', null, null);

        $this->assertNull($result['product_family']);
        $this->assertNull($result['product_subtype']);
    }

    public function test_bookcase_classified_as_storage(): void
    {
        $result = $this->classifier->classify('Bookcase VANDBORG 5 shelves light oak', 'Living room', null);

        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('storage', $result['product_subtype']);
    }

    public function test_shelving_unit_classified_as_storage(): void
    {
        $result = $this->classifier->classify('Shelving unit VANDBORG 5 shelves light oak colour', 'Furniture', null);

        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('storage', $result['product_subtype']);
    }

    public function test_drawer_chest_classified_as_storage(): void
    {
        $result = $this->classifier->classify('4 drawer chest TAPDRUP light oak colour', 'Bedroom', null);

        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('storage', $result['product_subtype']);
    }

    public function test_hallway_unit_classified_as_storage(): void
    {
        $result = $this->classifier->classify('Hallway unit BELLE 2 doors white/light oak colour', 'Furniture', null);

        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('storage', $result['product_subtype']);
    }

    public function test_sideboard_classified_as_storage(): void
    {
        $result = $this->classifier->classify('Sideboard LIMFJORDEN 2 dr 2 door light oak', 'Living room', null);

        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('storage', $result['product_subtype']);
    }

    public function test_candle_holder_not_lighting(): void
    {
        $result = $this->classifier->classifyFromName('Candle holder FLORA H20cm gold');

        $this->assertNotNull($result);
        $this->assertSame('home_accessories', $result['product_family']);
    }

    public function test_stool_classified_as_chair(): void
    {
        $result = $this->classifier->classifyFromName('Bar stool KLARUP black/oak');

        $this->assertNotNull($result);
        $this->assertSame('furniture', $result['product_family']);
        $this->assertSame('chair', $result['product_subtype']);
    }
}
