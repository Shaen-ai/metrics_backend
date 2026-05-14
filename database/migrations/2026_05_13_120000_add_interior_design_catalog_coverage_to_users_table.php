<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('interior_design_catalog_coverage_mode', 16)
                ->default('percent')
                ->after('site_published_at');
            $table->unsignedSmallInteger('interior_design_catalog_coverage_value')
                ->default(50)
                ->after('interior_design_catalog_coverage_mode');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'interior_design_catalog_coverage_mode',
                'interior_design_catalog_coverage_value',
            ]);
        });
    }
};
