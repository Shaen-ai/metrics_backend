<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('public_site_layout')->default('tunzone-classic-light')->after('use_custom_planner_catalog');
            $table->json('public_site_texts')->nullable()->after('public_site_layout');
            $table->json('public_site_theme')->nullable()->after('public_site_texts');
            $table->string('custom_design_key')->nullable()->after('public_site_theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'public_site_layout',
                'public_site_texts',
                'public_site_theme',
                'custom_design_key',
            ]);
        });
    }
};
