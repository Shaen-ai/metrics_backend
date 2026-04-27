<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_compatibilities', function (Blueprint $table) {
            $table->char('module_id', 36);
            $table->char('compatible_module_id', 36);

            $table->primary(['module_id', 'compatible_module_id']);
            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->foreign('compatible_module_id')->references('id')->on('modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_compatibilities');
    }
};
