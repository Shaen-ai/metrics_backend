<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_images', function (Blueprint $table) {
            $table->id();
            $table->char('module_id', 36);
            $table->text('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_images');
    }
};
