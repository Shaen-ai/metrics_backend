<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('plan_tier', 'growth')->update(['plan_tier' => 'business']);
        DB::table('users')->where('plan_tier', 'scale')->update(['plan_tier' => 'business_pro']);
    }

    /**
     * Best-effort rollback: cannot distinguish users who were native business_pro vs migrated from scale.
     */
    public function down(): void
    {
        DB::table('users')->where('plan_tier', 'business')->update(['plan_tier' => 'growth']);
    }
};
