<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_item_colors', function (Blueprint $table) {
            $table->id();
            $table->char('catalog_item_id', 36);
            $table->string('name');
            $table->string('hex', 7);
            $table->timestamps();

            $table->foreign('catalog_item_id')->references('id')->on('catalog_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_item_colors');
    }
};
