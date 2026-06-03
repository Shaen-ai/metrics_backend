<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scrape_urls')) {
            if (! Schema::hasColumn('scrape_urls', 'url_hash')) {
                Schema::table('scrape_urls', function (Blueprint $table) {
                    $table->string('url_hash', 64)->after('url')->default('');
                });
                DB::statement(
                    "UPDATE scrape_urls SET url_hash = SHA2(url, 256) WHERE url_hash = ''"
                );
                Schema::table('scrape_urls', function (Blueprint $table) {
                    $table->unique(['source_marketplace', 'url_hash'], 'scrape_urls_marketplace_url_unique');
                });
            }

            return;
        }

        Schema::create('scrape_urls', function (Blueprint $table) {
            $table->id();
            $table->string('source_marketplace', 32)->index();
            $table->string('url', 1024);
            $table->string('url_hash', 64);
            $table->string('external_id', 128)->nullable();
            $table->enum('status', ['pending', 'done', 'failed'])->default('pending')->index();
            $table->smallInteger('fail_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['source_marketplace', 'url_hash'], 'scrape_urls_marketplace_url_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_urls');
    }
};
