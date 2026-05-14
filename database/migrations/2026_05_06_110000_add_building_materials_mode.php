<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODE_ID = 'mode-building-materials';

    private const SUB_MODES = [
        ['id' => 'sub-building-doors', 'name' => 'Doors', 'slug' => 'building-doors', 'description' => 'Interior, exterior, metal, MDF, laminate and security doors', 'icon' => 'DoorClosed'],
        ['id' => 'sub-building-windows-glazing', 'name' => 'Windows & Glazing', 'slug' => 'building-windows-glazing', 'description' => 'PVC, aluminum, wood windows, balcony doors and glass types', 'icon' => 'PanelsTopLeft'],
        ['id' => 'sub-building-flooring', 'name' => 'Flooring', 'slug' => 'building-flooring', 'description' => 'Laminate, parquet, vinyl, ceramic and porcelain flooring', 'icon' => 'Layers'],
        ['id' => 'sub-building-wall-finishes', 'name' => 'Wall Finishes', 'slug' => 'building-wall-finishes', 'description' => 'Paint, wallpaper, wall panels and wall tiles', 'icon' => 'Paintbrush'],
        ['id' => 'sub-building-ceiling-materials', 'name' => 'Ceiling Materials', 'slug' => 'building-ceiling-materials', 'description' => 'Stretch ceilings, gypsum board, panels and suspended systems', 'icon' => 'PanelTop'],
    ];

    public function up(): void
    {
        $now = now();

        DB::table('modes')->updateOrInsert(
            ['id' => self::MODE_ID],
            [
                'name' => 'Building Materials',
                'slug' => 'building-materials',
                'description' => 'Doors, windows, flooring, wall finishes and ceiling materials',
                'icon' => 'PanelsTopLeft',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        foreach (self::SUB_MODES as $subMode) {
            DB::table('sub_modes')->updateOrInsert(
                ['id' => $subMode['id']],
                [
                    ...$subMode,
                    'mode_id' => self::MODE_ID,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('sub_modes')
            ->whereIn('id', array_column(self::SUB_MODES, 'id'))
            ->delete();

        DB::table('modes')
            ->where('id', self::MODE_ID)
            ->delete();
    }
};
