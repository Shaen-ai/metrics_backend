<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('materials')) {
            return;
        }

        Schema::table('materials', function (Blueprint $table) {
            if (! Schema::hasColumn('materials', 'texture_width_cm')) {
                $table->decimal('texture_width_cm', 8, 2)->nullable()->after('kerf_mm');
            }
            if (! Schema::hasColumn('materials', 'texture_height_cm')) {
                $table->decimal('texture_height_cm', 8, 2)->nullable()->after('texture_width_cm');
            }
            if (! Schema::hasColumn('materials', 'floor_layout_pattern')) {
                $table->string('floor_layout_pattern', 20)->nullable()->after('texture_height_cm');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('materials')) {
            return;
        }

        Schema::table('materials', function (Blueprint $table) {
            foreach (['floor_layout_pattern', 'texture_height_cm', 'texture_width_cm'] as $col) {
                if (Schema::hasColumn('materials', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
