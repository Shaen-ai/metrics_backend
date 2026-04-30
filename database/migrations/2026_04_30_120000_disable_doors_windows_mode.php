<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODE_ID = 'mode-doors-windows';

    private const SUB_MODE_IDS = [
        'sub-interior-doors',
        'sub-exterior-doors',
        'sub-sliding-doors',
        'sub-standard-windows',
        'sub-specialty-windows',
        'sub-french',
    ];

    public function up(): void
    {
        DB::table('users')
            ->where('selected_mode_id', self::MODE_ID)
            ->update([
                'selected_mode_id' => null,
                'selected_sub_mode_ids' => null,
                'updated_at' => now(),
            ]);

        foreach (DB::table('users')->whereNotNull('selected_sub_mode_ids')->get(['id', 'selected_sub_mode_ids']) as $user) {
            $selectedIds = json_decode($user->selected_sub_mode_ids, true);

            if (! is_array($selectedIds)) {
                continue;
            }

            $filteredIds = array_values(array_diff($selectedIds, self::SUB_MODE_IDS));

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
            ->whereIn('id', self::SUB_MODE_IDS)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        DB::table('modes')
            ->where('id', self::MODE_ID)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('modes')
            ->where('id', self::MODE_ID)
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);

        DB::table('sub_modes')
            ->whereIn('id', self::SUB_MODE_IDS)
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
