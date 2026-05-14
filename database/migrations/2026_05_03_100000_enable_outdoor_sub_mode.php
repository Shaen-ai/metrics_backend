<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SUB_MODE_ID = 'sub-outdoor';

    public function up(): void
    {
        DB::table('sub_modes')
            ->where('id', self::SUB_MODE_ID)
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('sub_modes')
            ->where('id', self::SUB_MODE_ID)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }
};
