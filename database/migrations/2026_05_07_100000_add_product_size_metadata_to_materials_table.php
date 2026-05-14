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
            if (! Schema::hasColumn('materials', 'product_width_cm')) {
                $table->decimal('product_width_cm', 8, 2)->nullable()->after('texture_height_cm');
            }
            if (! Schema::hasColumn('materials', 'product_height_cm')) {
                $table->decimal('product_height_cm', 8, 2)->nullable()->after('product_width_cm');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('materials')) {
            return;
        }

        Schema::table('materials', function (Blueprint $table) {
            foreach (['product_height_cm', 'product_width_cm'] as $col) {
                if (Schema::hasColumn('materials', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
