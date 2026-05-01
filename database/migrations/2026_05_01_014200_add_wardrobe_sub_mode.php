<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sub_modes')->updateOrInsert(
            ['id' => 'sub-wardrobe'],
            [
                'mode_id' => 'mode-furniture',
                'name' => 'Wardrobe',
                'slug' => 'wardrobe',
                'description' => 'Built-in wardrobes, closet systems, shelving',
                'icon' => 'DoorClosed',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('sub_modes')->where('id', 'sub-wardrobe')->delete();
    }
};
