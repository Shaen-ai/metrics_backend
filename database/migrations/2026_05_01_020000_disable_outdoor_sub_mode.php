<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SUB_MODE_ID = 'sub-outdoor';

    public function up(): void
    {
        foreach (DB::table('users')->whereNotNull('selected_sub_mode_ids')->get(['id', 'selected_sub_mode_ids']) as $user) {
            $selectedIds = json_decode($user->selected_sub_mode_ids, true);

            if (! is_array($selectedIds)) {
                continue;
            }

            $filteredIds = array_values(array_diff($selectedIds, [self::SUB_MODE_ID]));

            if ($filteredIds !== $selectedIds) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'selected_sub_mode_ids' => $filteredIds === [] ? null : json_encode($filteredIds),
                        'updated_at' => now(),
                    ]);
            }
        }

        DB::table('sub_modes')
            ->where('id', self::SUB_MODE_ID)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('sub_modes')
            ->where('id', self::SUB_MODE_ID)
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
