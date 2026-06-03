<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scraped_products')) {
            return;
        }

        Schema::table('scraped_products', function (Blueprint $table) {
            if (! Schema::hasColumn('scraped_products', 'product_family')) {
                $table->string('product_family', 48)->nullable()->index();
            }
            if (! Schema::hasColumn('scraped_products', 'product_subtype')) {
                $table->string('product_subtype', 48)->nullable()->index();
            }
            if (! Schema::hasColumn('scraped_products', 'material_tags')) {
                $table->json('material_tags')->nullable();
            }
            if (! Schema::hasColumn('scraped_products', 'color_tags')) {
                $table->json('color_tags')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('scraped_products')) {
            return;
        }

        Schema::table('scraped_products', function (Blueprint $table) {
            foreach (['product_family', 'product_subtype', 'material_tags', 'color_tags'] as $col) {
                if (Schema::hasColumn('scraped_products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
