<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan_tier', 32)->default('free')->after('paypal_email');
            $table->timestamp('trial_ends_at')->nullable()->after('plan_tier');
            $table->date('usage_month_start')->nullable()->after('trial_ends_at');
            $table->unsignedInteger('image3d_generations_this_month')->default(0)->after('usage_month_start');
            $table->unsignedInteger('ai_chat_messages_this_month')->default(0)->after('image3d_generations_this_month');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'plan_tier',
                'trial_ends_at',
                'usage_month_start',
                'image3d_generations_this_month',
                'ai_chat_messages_this_month',
            ]);
        });
    }
};
