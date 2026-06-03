<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scrape_urls', function (Blueprint $table) {
            $table->string('discovery_source', 16)->default('category')->after('external_id');
            $table->timestamp('last_seen_in_listing_at')->nullable()->after('scraped_at');
            $table->timestamp('last_verified_at')->nullable()->after('last_seen_in_listing_at');
        });

        Schema::table('scraped_products', function (Blueprint $table) {
            $table->timestamp('unavailable_at')->nullable()->after('scraped_at');
        });
    }

    public function down(): void
    {
        Schema::table('scrape_urls', function (Blueprint $table) {
            $table->dropColumn(['discovery_source', 'last_seen_in_listing_at', 'last_verified_at']);
        });

        Schema::table('scraped_products', function (Blueprint $table) {
            $table->dropColumn('unavailable_at');
        });
    }
};
