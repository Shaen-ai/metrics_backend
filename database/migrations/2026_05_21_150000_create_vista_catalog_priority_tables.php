<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vista_shop_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace', 32)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('vista_product_priorities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scraped_product_id')
                ->constrained('scraped_products')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('scraped_product_id');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vista_product_priorities');
        Schema::dropIfExists('vista_shop_priorities');
    }
};
