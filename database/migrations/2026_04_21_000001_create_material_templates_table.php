<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_templates', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('manufacturer', 64)->index();
            $table->string('external_code', 64)->nullable()->index();
            $table->string('name');
            $table->string('type', 50);
            $table->json('categories');
            $table->string('category', 50);
            $table->string('color');
            $table->string('color_hex', 7)->nullable();
            $table->string('color_code', 20)->nullable();
            $table->string('unit', 20)->default('sqm');
            $table->text('image_url')->nullable();
            $table->text('source_url')->nullable();
            $table->decimal('sheet_width_cm', 8, 2)->nullable();
            $table->decimal('sheet_height_cm', 8, 2)->nullable();
            $table->string('grain_direction', 20)->nullable();
            $table->decimal('kerf_mm', 5, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_templates');
    }
};
