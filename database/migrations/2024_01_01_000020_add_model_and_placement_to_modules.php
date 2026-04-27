<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('model_url')->nullable()->after('is_active');
            $table->string('model_job_id')->nullable()->after('model_url');
            $table->string('model_status')->nullable()->after('model_job_id');
            $table->text('model_error')->nullable()->after('model_status');
            $table->string('placement_type')->default('floor')->after('model_error');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['model_url', 'model_job_id', 'model_status', 'model_error', 'placement_type']);
        });
    }
};
