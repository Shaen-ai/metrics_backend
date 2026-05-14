<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interior_design_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('admin_id', 36)->index();
            $table->string('style', 48)->default('modern');
            $table->json('room_analysis')->nullable();
            $table->json('design_brief')->nullable();
            $table->text('latest_prompt')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('interior_design_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id')->index();
            $table->string('file_path');
            $table->text('prompt_used')->nullable();
            $table->string('type', 24)->default('generated');
            $table->string('mime_type', 48)->default('image/png');
            $table->unsignedInteger('file_size_bytes')->default(0);
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('interior_design_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interior_design_images');
        Schema::dropIfExists('interior_design_sessions');
    }
};
