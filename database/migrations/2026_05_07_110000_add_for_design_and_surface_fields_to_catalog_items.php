<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->boolean('for_design')->default(false)->after('is_active');
            $table->decimal('surface_texture_width_cm', 8, 2)->nullable()->after('for_design');
            $table->decimal('surface_texture_height_cm', 8, 2)->nullable()->after('surface_texture_width_cm');
            $table->decimal('surface_item_width_cm', 8, 2)->nullable()->after('surface_texture_height_cm');
            $table->decimal('surface_item_height_cm', 8, 2)->nullable()->after('surface_item_width_cm');
            $table->string('surface_layout_pattern')->nullable()->after('surface_item_height_cm');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropColumn([
                'for_design',
                'surface_texture_width_cm',
                'surface_texture_height_cm',
                'surface_item_width_cm',
                'surface_item_height_cm',
                'surface_layout_pattern',
            ]);
        });
    }
};
