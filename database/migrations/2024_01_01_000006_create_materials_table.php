<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('admin_id', 36);
            $table->char('mode_id', 36);
            $table->char('sub_mode_id', 36)->nullable();
            $table->string('name');
            $table->string('type');
            $table->string('category');
            $table->string('color');
            $table->string('color_hex', 7)->nullable();
            $table->string('color_code', 20)->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('price_per_unit', 10, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('unit', 20);
            $table->text('image')->nullable();
            $table->text('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('mode_id')->references('id')->on('modes')->cascadeOnDelete();
            $table->foreign('sub_mode_id')->references('id')->on('sub_modes')->nullOnDelete();
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
