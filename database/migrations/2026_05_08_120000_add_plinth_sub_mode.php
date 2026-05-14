<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODE_ID = 'mode-building-materials';

    private const SUB_MODE = [
        'id' => 'sub-building-plinth',
        'name' => 'Plinth / Skirting',
        'slug' => 'building-plinth',
        'description' => 'MDF, PVC, metal and stone skirting boards',
        'icon' => 'AlignVerticalJustifyStart',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('sub_modes')->updateOrInsert(
            ['id' => self::SUB_MODE['id']],
            [
                ...self::SUB_MODE,
                'mode_id' => self::MODE_ID,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('sub_modes')
            ->where('id', self::SUB_MODE['id'])
            ->delete();
    }
};
