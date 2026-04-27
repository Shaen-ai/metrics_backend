<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_modes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('mode_id', 36);
            $table->string('name');
            $table->string('slug');
            $table->text('description');
            $table->string('icon');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('mode_id')->references('id')->on('modes')->cascadeOnDelete();
            $table->unique(['mode_id', 'slug']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('selected_mode_id')->references('id')->on('modes')->nullOnDelete();
            $table->foreign('selected_sub_mode_id')->references('id')->on('sub_modes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['selected_mode_id']);
            $table->dropForeign(['selected_sub_mode_id']);
        });
        Schema::dropIfExists('sub_modes');
    }
};
