<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_connection_points', function (Blueprint $table) {
            $table->id();
            $table->char('module_id', 36);
            $table->string('position', 6);
            $table->string('type')->default('standard');
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_connection_points');
    }
};
