<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Temporary test grant — reset before public launch. */
    private const TEST_BALANCE = 1000;

    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'token_balance')) {
            DB::table('users')->update(['token_balance' => self::TEST_BALANCE]);
        }

        if (Schema::hasTable('anonymous_token_sessions') && Schema::hasColumn('anonymous_token_sessions', 'token_balance')) {
            DB::table('anonymous_token_sessions')->update(['token_balance' => self::TEST_BALANCE]);
        }
    }

    public function down(): void
    {
        // Intentionally no-op: previous balances are not recoverable.
    }
};
