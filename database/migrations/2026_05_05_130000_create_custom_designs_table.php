<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_designs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('admin_id', 36);
            $table->char('owner_user_id', 36)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('room_name', 160)->nullable();
            $table->text('notes')->nullable();
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_email')->nullable();
            $table->json('design');
            $table->string('snapshot_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['admin_id', 'status', 'created_at']);
            $table->index(['owner_user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_designs');
    }
};
