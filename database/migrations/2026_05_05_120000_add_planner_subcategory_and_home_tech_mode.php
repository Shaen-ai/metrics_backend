<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->string('planner_subcategory', 255)->nullable()->after('additional_categories');
        });

        $now = now()->toISOString();

        DB::table('modes')->insertOrIgnore([
            'id' => 'mode-home-tech',
            'name' => 'Home & Tech',
            'slug' => 'home-tech',
            'description' => 'Major and small appliances, climate, electronics, lighting, kitchenware',
            'icon' => 'Refrigerator',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subModes = [
            ['id' => 'sub-major-appliances', 'name' => 'Major appliances', 'slug' => 'major-appliances', 'description' => 'Refrigerators, ovens, cooktops, dishwashers', 'icon' => 'Refrigerator'],
            ['id' => 'sub-small-appliances', 'name' => 'Small appliances', 'slug' => 'small-appliances', 'description' => 'Mixers, kettles, coffee makers', 'icon' => 'Coffee'],
            ['id' => 'sub-climate-air', 'name' => 'Climate & air', 'slug' => 'climate-air', 'description' => 'Air conditioners, purifiers, fans', 'icon' => 'Fan'],
            ['id' => 'sub-smart-home-electronics', 'name' => 'Smart home & electronics', 'slug' => 'smart-home-electronics', 'description' => 'Speakers, screens, hubs', 'icon' => 'Cpu'],
            ['id' => 'sub-kitchenware-cutlery', 'name' => 'Kitchenware & cutlery', 'slug' => 'kitchenware-cutlery', 'description' => 'Dishes, knives, cookware', 'icon' => 'Utensils'],
            ['id' => 'sub-home-lighting', 'name' => 'Lighting', 'slug' => 'home-lighting', 'description' => 'Lamps, fixed lighting, bulbs', 'icon' => 'LampDesk'],
        ];

        foreach ($subModes as $row) {
            DB::table('sub_modes')->insertOrIgnore([
                'id' => $row['id'],
                'mode_id' => 'mode-home-tech',
                'name' => $row['name'],
                'slug' => $row['slug'],
                'description' => $row['description'],
                'icon' => $row['icon'],
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropColumn('planner_subcategory');
        });
        // Intentionally do not drop mode-home-tech / sub-modes — catalog rows may FK to them (cascade would delete merchant data).
    }
};
