<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymous_token_sessions', function (Blueprint $table) {
            $table->uuid('device_id')->primary();
            $table->unsignedInteger('token_balance')->default(0);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anonymous_token_sessions');
    }
};
