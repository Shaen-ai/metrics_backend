<?php

namespace Tests\Unit;

use App\Support\VistaFilePath;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VistaFilePathTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('vista_files');
    }

    public function test_room_version_path_uses_generated_layout(): void
    {
        $path = VistaFilePath::roomVersion('user-1', 'proj-1', 'room_1', 1, 0, 'jpg');

        $this->assertSame('user-1/proj-1/generated/rooms/room_1/v001-angle0.jpg', $path);
    }

    public function test_floor_plan_path_uses_uploaded_layout(): void
    {
        $path = VistaFilePath::floorPlan('user-1', 'proj-1', 'jpg');

        $this->assertSame('user-1/proj-1/uploaded/floor-plan.jpg', $path);
    }

    public function test_legacy_room_path_maps_to_generated_layout(): void
    {
        $legacy = 'projects/user-1/proj-1/rooms/room_1/v001-angle0.jpg';
        $candidates = VistaFilePath::pathCandidates($legacy);
        $expected = VistaFilePath::roomVersion('user-1', 'proj-1', 'room_1', 1, 0, 'jpg');

        $this->assertContains($expected, $candidates);
    }

    public function test_resolve_existing_path_finds_file_at_legacy_mapped_location(): void
    {
        $legacy = 'projects/user-1/proj-1/rooms/room_1/v001-angle0.jpg';
        $actual = VistaFilePath::roomVersion('user-1', 'proj-1', 'room_1', 1, 0, 'jpg');
        Storage::disk('vista_files')->put($actual, 'fake-image');

        $resolved = VistaFilePath::resolveExistingPath(Storage::disk('vista_files'), $legacy);

        $this->assertSame($actual, $resolved);
    }

    public function test_resolve_existing_path_tries_extension_swap(): void
    {
        $stored = 'user-1/proj-1/generated/rooms/room_1/v001-angle0.jpg';
        $pngPath = 'user-1/proj-1/generated/rooms/room_1/v001-angle0.png';
        Storage::disk('vista_files')->put($pngPath, 'fake-png');

        $resolved = VistaFilePath::resolveExistingPath(Storage::disk('vista_files'), $stored);

        $this->assertSame($pngPath, $resolved);
    }

    public function test_cover_fallback_prefers_existing_floor_plan_when_render_missing(): void
    {
        $cover = VistaFilePath::roomVersion('user-1', 'proj-1', 'room_1', 1, 0, 'jpg');
        $floorPlan = VistaFilePath::floorPlan('user-1', 'proj-1', 'jpg');
        Storage::disk('vista_files')->put($floorPlan, 'floor-plan');

        $disk = Storage::disk('vista_files');
        $resolvedCover = VistaFilePath::resolveExistingPath($disk, $cover);
        $resolvedFloorPlan = VistaFilePath::resolveExistingPath($disk, $floorPlan);

        $this->assertNull($resolvedCover);
        $this->assertSame($floorPlan, $resolvedFloorPlan);
    }
}
