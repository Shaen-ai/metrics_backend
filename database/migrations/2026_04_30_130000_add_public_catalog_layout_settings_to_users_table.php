<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('public_catalog_layouts')->nullable()->after('public_site_theme');
            $table->string('public_catalog_default_layout')->default('grid')->after('public_catalog_layouts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'public_catalog_layouts',
                'public_catalog_default_layout',
            ]);
        });
    }
};
