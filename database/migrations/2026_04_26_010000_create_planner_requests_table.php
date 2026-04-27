<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_requests', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('admin_id', 36);
            $table->text('text');
            $table->json('image_paths')->nullable();
            $table->json('ai_interpretation')->nullable();
            $table->json('result')->nullable();
            $table->decimal('estimated_price', 10, 2)->default(0);
            $table->string('status', 20)->default('completed');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['admin_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_requests');
    }
};
