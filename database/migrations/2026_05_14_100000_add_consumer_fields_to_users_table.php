<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'user_type')) {
                $table->string('user_type', 16)->default('business')->after('id');
            }
            if (! Schema::hasColumn('users', 'wallet_balance_usd')) {
                $table->decimal('wallet_balance_usd', 10, 2)->default(0)->after('stripe_subscription_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['user_type', 'wallet_balance_usd']);
        });
    }
};
