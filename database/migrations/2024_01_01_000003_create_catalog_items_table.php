<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('admin_id', 36);
            $table->char('mode_id', 36);
            $table->char('sub_mode_id', 36);
            $table->string('name');
            $table->text('description');
            $table->decimal('width', 10, 2);
            $table->decimal('height', 10, 2);
            $table->decimal('depth', 10, 2);
            $table->string('dimension_unit', 4)->default('cm');
            $table->decimal('price', 10, 2);
            $table->string('currency', 10)->default('USD');
            $table->unsignedInteger('delivery_days')->default(14);
            $table->string('category');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('mode_id')->references('id')->on('modes')->cascadeOnDelete();
            $table->foreign('sub_mode_id')->references('id')->on('sub_modes')->cascadeOnDelete();
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
