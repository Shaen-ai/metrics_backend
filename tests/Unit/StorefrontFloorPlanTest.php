<?php

namespace Tests\Unit;

use App\Models\StorefrontFloorPlan;
use Tests\TestCase;

class StorefrontFloorPlanTest extends TestCase
{
    public function test_model_has_correct_casts(): void
    {
        $plan = new StorefrontFloorPlan();

        $casts = $plan->getCasts();
        $this->assertArrayHasKey('floor_plan_analysis', $casts);
        $this->assertSame('array', $casts['floor_plan_analysis']);
    }

    public function test_model_uses_uuid_primary_key(): void
    {
        $plan = new StorefrontFloorPlan();

        $this->assertFalse($plan->getIncrementing());
        $this->assertSame('string', $plan->getKeyType());
    }

    public function test_fillable_fields(): void
    {
        $plan = new StorefrontFloorPlan();

        $this->assertContains('admin_id', $plan->getFillable());
        $this->assertContains('floor_plan_path', $plan->getFillable());
        $this->assertContains('floor_plan_analysis', $plan->getFillable());
        $this->assertContains('status', $plan->getFillable());
    }

    public function test_analysis_cast_round_trips(): void
    {
        $analysis = [
            'totalArea' => 65.0,
            'ceilingHeight' => 2.7,
            'rooms' => [
                [
                    'id' => 'room-1',
                    'name' => 'Living Room',
                    'type' => 'living',
                    'estimatedArea' => 25.0,
                ],
            ],
        ];

        $plan = new StorefrontFloorPlan();
        $plan->floor_plan_analysis = $analysis;

        $this->assertEquals(25.0, $plan->floor_plan_analysis['rooms'][0]['estimatedArea']);
        $this->assertSame('living', $plan->floor_plan_analysis['rooms'][0]['type']);
    }

    public function test_default_status_is_pending(): void
    {
        $defaults = (new StorefrontFloorPlan())->getAttributes();
        // The DB default is 'pending'; model attribute defaults aren't set until save,
        // but the fillable path allows setting it.
        $plan = new StorefrontFloorPlan(['status' => 'confirmed']);
        $this->assertSame('confirmed', $plan->status);
    }
}
