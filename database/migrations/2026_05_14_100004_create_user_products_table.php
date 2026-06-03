<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_products')) {
            return;
        }

        Schema::create('user_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id', 36)->index();
            $table->string('source_type', 24)->default('url');
            $table->string('source_url', 1024)->nullable();

            $table->string('name', 512);
            $table->string('name_en', 512)->nullable();
            $table->text('description')->nullable();
            $table->string('category', 256)->nullable();

            $table->unsignedInteger('price')->nullable();
            $table->string('currency', 8)->default('AMD');

            $table->unsignedSmallInteger('width_cm')->nullable();
            $table->unsignedSmallInteger('depth_cm')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();

            $table->json('images')->nullable();
            $table->string('main_image_url', 1024)->nullable();
            $table->string('uploaded_image_path', 512)->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_products');
    }
};
