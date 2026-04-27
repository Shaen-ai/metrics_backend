<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize legacy 360×280 cm sheet records to 360×180 cm (matches admin / API defaults).
     */
    public function up(): void
    {
        foreach (['materials', 'material_templates'] as $table) {
            DB::table($table)
                ->whereNotNull('sheet_width_cm')
                ->whereNotNull('sheet_height_cm')
                ->whereRaw('ABS(sheet_width_cm - 360) < 0.01')
                ->whereRaw('ABS(sheet_height_cm - 280) < 0.01')
                ->update(['sheet_height_cm' => 180]);
        }
    }

    public function down(): void
    {
        foreach (['materials', 'material_templates'] as $table) {
            DB::table($table)
                ->whereNotNull('sheet_width_cm')
                ->whereNotNull('sheet_height_cm')
                ->whereRaw('ABS(sheet_width_cm - 360) < 0.01')
                ->whereRaw('ABS(sheet_height_cm - 180) < 0.01')
                ->update(['sheet_height_cm' => 280]);
        }
    }
};
