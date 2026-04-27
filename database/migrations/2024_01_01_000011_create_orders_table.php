<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('admin_id', 36);
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->string('type', 10);
            $table->decimal('total_price', 10, 2);
            $table->string('status', 10)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
