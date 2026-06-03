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
            if (! Schema::hasColumn('scraped_products', 'priority')) {
                $table->unsignedSmallInteger('priority')->nullable()->after('sku');
                $table->index('priority');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('scraped_products')) {
            return;
        }

        Schema::table('scraped_products', function (Blueprint $table) {
            if (Schema::hasColumn('scraped_products', 'priority')) {
                $table->dropIndex(['priority']);
                $table->dropColumn('priority');
            }
        });
    }
};
