<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scraped_products', function (Blueprint $table) {
            if (! Schema::hasColumn('scraped_products', 'embedding_text')) {
                $table->mediumText('embedding_text')->nullable()->after('color_tags');
            }
            if (! Schema::hasColumn('scraped_products', 'embedding_text_version')) {
                $table->string('embedding_text_version', 16)->nullable()->after('embedding_text');
            }
            if (! Schema::hasColumn('scraped_products', 'embedded_at')) {
                $table->timestamp('embedded_at')->nullable()->after('embedding_text_version');
            }
            if (! Schema::hasColumn('scraped_products', 'cutout_image_url')) {
                $table->string('cutout_image_url', 1024)->nullable()->after('embedded_at');
            }
            if (! Schema::hasColumn('scraped_products', 'cutout_confidence')) {
                $table->decimal('cutout_confidence', 4, 3)->nullable()->after('cutout_image_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scraped_products', function (Blueprint $table) {
            foreach (['cutout_confidence', 'cutout_image_url', 'embedded_at', 'embedding_text_version', 'embedding_text'] as $col) {
                if (Schema::hasColumn('scraped_products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
