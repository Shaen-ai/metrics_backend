<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Originally cleared all materials — that must not run on deploy or fresh installs.
 * Kept as a no-op so existing migration history stays valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        // intentionally empty
    }

    public function down(): void
    {
        // intentionally empty
    }
};
