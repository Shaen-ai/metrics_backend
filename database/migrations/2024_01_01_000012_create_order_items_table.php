<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->char('order_id', 36);
            $table->string('item_type', 10);
            $table->char('item_id', 36)->nullable();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->decimal('price', 10, 2);
            $table->json('custom_data')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
