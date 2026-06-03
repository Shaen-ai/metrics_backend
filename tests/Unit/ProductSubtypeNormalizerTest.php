<?php

namespace Tests\Unit;

use App\Services\Catalog\ProductSubtypeNormalizer;
use Tests\CreatesApplication;
use Tests\TestCase;

class ProductSubtypeNormalizerTest extends TestCase
{
    use CreatesApplication;

    public function test_normalize_maps_free_form_furniture_subtypes(): void
    {
        $this->assertSame('chair', ProductSubtypeNormalizer::normalize('furniture', 'armchair'));
        $this->assertSame('coffee_table', ProductSubtypeNormalizer::normalize('furniture', 'coffee table'));
        $this->assertSame('tv_stand', ProductSubtypeNormalizer::normalize('furniture', 'tv stand'));
    }

    public function test_normalize_maps_flooring_and_lighting_subtypes(): void
    {
        $this->assertSame('rug', ProductSubtypeNormalizer::normalize('flooring', 'area rug'));
        $this->assertSame('pendant', ProductSubtypeNormalizer::normalize('lighting', 'pendant ceiling light'));
        $this->assertSame('curtain', ProductSubtypeNormalizer::normalize('window_treatments', 'floor-length drape curtains'));
    }

    public function test_subtypes_match_treats_armchair_as_chair(): void
    {
        $this->assertTrue(ProductSubtypeNormalizer::subtypesMatch('furniture', 'armchair', 'chair'));
        $this->assertFalse(ProductSubtypeNormalizer::subtypesMatch('furniture', 'sofa', 'chair'));
    }

    public function test_chair_synonyms_from_briefs_resolve_to_chair(): void
    {
        // Real slots that appeared in production logs and were dropping all candidates
        // because they didn't equate to plain "chair" — even when a Chair SKU was available.
        $this->assertSame('chair', ProductSubtypeNormalizer::normalize('furniture', 'lounge_chair'));
        $this->assertSame('chair', ProductSubtypeNormalizer::normalize('furniture', 'lounge chair'));
        $this->assertSame('chair', ProductSubtypeNormalizer::normalize('furniture', 'dining_chair'));
        $this->assertSame('chair', ProductSubtypeNormalizer::normalize('furniture', 'stool'));
        $this->assertFalse(
            ProductSubtypeNormalizer::subtypeConflictsWithSlot('furniture', 'lounge_chair', 'chair'),
            'a Chair SKU must satisfy a lounge_chair slot',
        );
    }

    public function test_normalize_maps_gemini_brief_subtypes(): void
    {
        $this->assertSame('tv_stand', ProductSubtypeNormalizer::normalize('furniture', 'media_console_low'));
        $this->assertSame('storage', ProductSubtypeNormalizer::normalize('furniture', 'storage_ottoman'));
        $this->assertSame('sheer', ProductSubtypeNormalizer::normalize('window_treatments', 'sheer_linen_panels'));
        $this->assertSame('wallpaper', ProductSubtypeNormalizer::normalize('walls', 'accent_wall_paint'));
    }

    public function test_subtype_conflicts_skips_missing_product_taxonomy(): void
    {
        $this->assertFalse(ProductSubtypeNormalizer::subtypeConflictsWithSlot('furniture', 'sofa', null));
        $this->assertTrue(ProductSubtypeNormalizer::subtypeConflictsWithSlot('furniture', 'sofa', 'vase'));
    }

    /**
     * Documents the conflicts that the rerank must reject in CatalogSlotResolver
     * (even at the stage-2 fallback). If any of these become "false" silently, a
     * TV/vase/chair could fill a sofa/coffee_table/tv_stand slot again.
     */
    public function test_known_cross_subtype_substitutions_are_rejected(): void
    {
        $this->assertTrue(
            ProductSubtypeNormalizer::subtypeConflictsWithSlot('furniture', 'sofa', 'tv'),
            'TV must never fill a sofa slot',
        );
        $this->assertTrue(
            ProductSubtypeNormalizer::subtypeConflictsWithSlot('furniture', 'coffee_table', 'vase'),
            'Vase must never fill a coffee_table slot',
        );
        $this->assertTrue(
            ProductSubtypeNormalizer::subtypeConflictsWithSlot('furniture', 'tv_stand', 'chair'),
            'Chair must never fill a tv_stand slot',
        );
        $this->assertTrue(
            ProductSubtypeNormalizer::subtypeConflictsWithSlot('lighting', 'floor', 'ceiling'),
            'A ceiling fixture must never fill a floor-lamp slot (Bed-15868 class of bug)',
        );
    }
}
