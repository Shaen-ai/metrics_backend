<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index the columns the Qdrant catalog-sync hot path filters on.
 *
 * CatalogQdrantSyncService repeatedly runs queries shaped like:
 *   whereNotNull('embedded_at')->where('in_stock', ?)->whereNull/NotNull('unavailable_at')
 * Without these indexes each sync pass full-scans scraped_products.
 *
 * Additive + reversible only — indexes never change query results.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scraped_products')) {
            return;
        }

        Schema::table('scraped_products', function (Blueprint $table) {
            // Leading low-cardinality in_stock + embedded_at range — matches the sync filters.
            if (! Schema::hasIndex('scraped_products', 'scraped_products_in_stock_embedded_at_index')) {
                $table->index(['in_stock', 'embedded_at'], 'scraped_products_in_stock_embedded_at_index');
            }
            if (! Schema::hasIndex('scraped_products', 'scraped_products_unavailable_at_index')) {
                $table->index('unavailable_at', 'scraped_products_unavailable_at_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('scraped_products')) {
            return;
        }

        Schema::table('scraped_products', function (Blueprint $table) {
            if (Schema::hasIndex('scraped_products', 'scraped_products_in_stock_embedded_at_index')) {
                $table->dropIndex('scraped_products_in_stock_embedded_at_index');
            }
            if (Schema::hasIndex('scraped_products', 'scraped_products_unavailable_at_index')) {
                $table->dropIndex('scraped_products_unavailable_at_index');
            }
        });
    }
};
