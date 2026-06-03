<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id', 36)->index();
            $table->string('title', 255)->default('Design Project');
            $table->string('status', 24)->default('pending');
            $table->string('style', 50)->default('modern-neutral');
            $table->decimal('total_area_m2', 8, 2)->nullable();
            $table->unsignedSmallInteger('family_members')->default(2);
            $table->string('budget_tier', 20)->default('mid');
            $table->text('wishes')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('floor_plan_path', 500)->nullable();
            $table->json('floor_plan_analysis')->nullable();
            $table->json('master_concept')->nullable();
            $table->json('room_results')->nullable();
            $table->json('technical_drawings')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('current_phase', 50)->nullable();
            $table->string('current_room', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->decimal('cost_usd', 8, 4)->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('design_project_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();
            $table->string('room_id', 50);
            $table->unsignedTinyInteger('angle_index')->default(0);
            $table->string('file_path', 500);
            $table->string('mime_type', 50)->default('image/png');
            $table->unsignedInteger('file_size_bytes')->default(0);
            $table->string('type', 24)->default('room_render');
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('design_projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_project_images');
        Schema::dropIfExists('design_projects');
    }
};
