<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vista_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id', 36)->index();
            $table->string('mode', 16)->default('quick_room'); // quick_room | project
            $table->string('title', 255)->default('Untitled Design');
            $table->string('cover_image_path', 500)->nullable();
            $table->string('status', 24)->default('active'); // active | archived

            // Quick Room fields
            $table->string('style', 48)->nullable();
            $table->string('room_image_path', 500)->nullable();
            $table->json('room_extra_paths')->nullable();
            $table->json('room_analysis')->nullable();
            $table->json('room_geometry')->nullable();

            // Project Mode fields
            $table->string('floor_plan_path', 500)->nullable();
            $table->json('floor_plan_analysis')->nullable();
            $table->json('master_concept')->nullable();
            $table->json('room_results')->nullable();
            $table->json('preferences')->nullable();
            $table->string('pdf_path', 500)->nullable();

            // Metadata
            $table->timestamp('last_interaction_at')->nullable();
            $table->unsignedSmallInteger('message_count')->default(0);
            $table->unsignedSmallInteger('version_count')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'mode', 'last_interaction_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('vista_project_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();

            $table->string('file_path', 500);
            $table->string('mime_type', 48)->default('image/png');
            $table->unsignedInteger('file_size_bytes')->default(0);

            $table->text('prompt_used')->nullable();
            $table->text('feedback')->nullable();
            $table->json('design_brief')->nullable();
            $table->json('products_used')->nullable();
            $table->json('room_geometry')->nullable();

            $table->unsignedSmallInteger('version_number')->default(1);
            $table->string('type', 24)->default('generated'); // generated | edited | regenerated

            // For Project Mode: which room
            $table->string('room_id', 50)->nullable();
            $table->unsignedTinyInteger('angle_index')->default(0);

            $table->timestamp('created_at')->nullable();

            $table->foreign('project_id')->references('id')->on('vista_projects')->cascadeOnDelete();
        });

        Schema::create('vista_project_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();

            $table->string('role', 16); // user | assistant | system
            $table->string('content_type', 24)->default('text'); // text | image_upload | generation | action

            $table->text('text')->nullable();
            $table->uuid('version_id')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_mime', 48)->nullable();

            $table->unsignedInteger('sequence');
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'sequence']);
            $table->foreign('project_id')->references('id')->on('vista_projects')->cascadeOnDelete();
            $table->foreign('version_id')->references('id')->on('vista_project_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vista_project_messages');
        Schema::dropIfExists('vista_project_versions');
        Schema::dropIfExists('vista_projects');
    }
};
