<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scraped_products')) {
            // Table exists (e.g., created manually) — just ensure the url_hash column and indexes exist
            if (! Schema::hasColumn('scraped_products', 'external_url_hash')) {
                Schema::table('scraped_products', function (Blueprint $table) {
                    $table->string('external_url_hash', 64)->after('external_url')->default('');
                });
                // Backfill hashes for existing rows
                DB::statement(
                    "UPDATE scraped_products SET external_url_hash = SHA2(external_url, 256) WHERE external_url_hash = ''"
                );
                Schema::table('scraped_products', function (Blueprint $table) {
                    $table->unique(['source_marketplace', 'external_url_hash'], 'scraped_products_marketplace_url_unique');
                });
            }

            return;
        }

        Schema::create('scraped_products', function (Blueprint $table) {
            $table->id();
            $table->string('source_marketplace', 32)->index();
            $table->string('external_url', 1024);
            $table->string('external_url_hash', 64);
            $table->string('external_id', 128)->nullable()->index();
            $table->string('slug', 512)->nullable();

            $table->string('name', 512);
            $table->string('name_en', 512)->nullable();
            $table->text('description')->nullable();
            $table->text('description_en')->nullable();
            $table->string('category', 256)->nullable();
            $table->string('category_en', 256)->nullable();
            $table->string('brand', 128)->nullable();

            $table->unsignedInteger('price')->default(0);
            $table->string('currency', 8)->default('AMD');
            $table->unsignedInteger('old_price')->nullable();
            $table->boolean('in_stock')->default(true);

            $table->unsignedSmallInteger('width_cm')->nullable();
            $table->unsignedSmallInteger('depth_cm')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->string('dimensions_raw', 128)->nullable();
            $table->boolean('has_dimensions')->default(false);

            $table->json('images')->nullable();
            $table->string('main_image_url', 1024)->nullable();
            $table->json('attributes')->nullable();

            $table->string('sku', 128)->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['source_marketplace', 'external_url_hash'], 'scraped_products_marketplace_url_unique');
        });

        // MySQL FULLTEXT index for search
        if (config('database.default') === 'mysql') {
            DB::statement(
                'ALTER TABLE scraped_products ADD FULLTEXT idx_scraped_search (name, name_en, category_en, brand)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scraped_products');
    }
};
