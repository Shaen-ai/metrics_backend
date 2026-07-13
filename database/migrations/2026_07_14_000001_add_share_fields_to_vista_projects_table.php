<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vista_projects', function (Blueprint $table) {
            $table->boolean('share_enabled')->default(false)->after('status');
            $table->string('share_token', 64)->nullable()->unique()->after('share_enabled');
            $table->timestamp('share_enabled_at')->nullable()->after('share_token');
        });
    }

    public function down(): void
    {
        Schema::table('vista_projects', function (Blueprint $table) {
            $table->dropColumn(['share_enabled', 'share_token', 'share_enabled_at']);
        });
    }
};
