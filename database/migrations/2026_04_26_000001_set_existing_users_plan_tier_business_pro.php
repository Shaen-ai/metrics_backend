<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time: assign Business Pro entitlements to all existing users.
     * New signups still default to `free` via AuthController.
     */
    public function up(): void
    {
        DB::table('users')->update(['plan_tier' => 'business_pro']);
    }

    public function down(): void
    {
        // Previous tiers are not tracked; revert to free for rollback only.
        DB::table('users')->update(['plan_tier' => 'free']);
    }
};
