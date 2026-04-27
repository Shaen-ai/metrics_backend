<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_materials', function (Blueprint $table) {
            $table->unsignedBigInteger('order_item_id');
            $table->char('material_id', 36);

            $table->primary(['order_item_id', 'material_id']);
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('materials')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_materials');
    }
};
