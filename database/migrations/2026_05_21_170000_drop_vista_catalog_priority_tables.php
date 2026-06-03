<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('vista_product_priorities');
        Schema::dropIfExists('vista_shop_priorities');
    }

    public function down(): void
    {
        // Tables replaced by scraped_products.priority — not recreated.
    }
};
