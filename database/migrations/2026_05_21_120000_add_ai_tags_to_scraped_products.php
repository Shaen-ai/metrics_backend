<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scraped_products', function (Blueprint $table) {
            if (! Schema::hasColumn('scraped_products', 'ai_tags')) {
                $table->json('ai_tags')->nullable()->after('color_tags');
            }
            if (! Schema::hasColumn('scraped_products', 'ai_enriched_at')) {
                $table->timestamp('ai_enriched_at')->nullable()->after('ai_tags');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scraped_products', function (Blueprint $table) {
            foreach (['ai_enriched_at', 'ai_tags'] as $col) {
                if (Schema::hasColumn('scraped_products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
