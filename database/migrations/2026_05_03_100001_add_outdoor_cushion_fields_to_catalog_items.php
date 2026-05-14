<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->boolean('supports_outdoor_cushions')->default(false);
            $table->json('outdoor_cushion_defaults')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropColumn(['supports_outdoor_cushions', 'outdoor_cushion_defaults']);
        });
    }
};
