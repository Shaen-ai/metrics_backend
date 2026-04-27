<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('language', 'hy')->update(['language' => 'en']);
    }

    public function down(): void
    {
        // Irreversible: prior `hy` users cannot be distinguished from native `en`.
    }
};
