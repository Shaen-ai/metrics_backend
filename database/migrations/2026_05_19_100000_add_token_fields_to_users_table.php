<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'token_balance')) {
                $table->unsignedInteger('token_balance')->default(0);
            }
            if (! Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 32)->nullable()->unique()->after('token_balance');
            }
            if (! Schema::hasColumn('users', 'referred_by_user_id')) {
                $table->uuid('referred_by_user_id')->nullable()->after('referral_code');
                $table->foreign('referred_by_user_id')->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'referral_tokens_earned')) {
                $table->unsignedInteger('referral_tokens_earned')->default(0)->after('referred_by_user_id');
            }
            if (! Schema::hasColumn('users', 'first_login_bonus_granted_at')) {
                $table->timestamp('first_login_bonus_granted_at')->nullable()->after('referral_tokens_earned');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'referred_by_user_id')) {
                $table->dropForeign(['referred_by_user_id']);
            }
            $cols = ['token_balance', 'referral_code', 'referred_by_user_id', 'referral_tokens_earned', 'first_login_bonus_granted_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
