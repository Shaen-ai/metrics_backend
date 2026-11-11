<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_floor_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('admin_id');
            $table->string('floor_plan_path', 500)->nullable();
            $table->json('floor_plan_analysis')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamps();

            $table->foreign('admin_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_floor_plans');
    }
};
