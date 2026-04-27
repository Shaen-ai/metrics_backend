<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds laminate/wood/worktop sheet metadata to materials:
 *  - sheet_width_cm / sheet_height_cm: physical size of the delivered sheet
 *    (used by the planner's virtual-cut packer). Nullable — consumers coalesce
 *    missing values to 360 × 180 cm so existing rows work unchanged.
 *  - grain_direction: which sheet axis the grain runs along, or 'none' for
 *    materials the packer may rotate freely.
 *  - kerf_mm: saw-blade kerf accounted for between adjacent cuts. Nullable —
 *    defaults to 3.0 mm at read time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('materials')) {
            return;
        }

        Schema::table('materials', function (Blueprint $table) {
            if (!Schema::hasColumn('materials', 'sheet_width_cm')) {
                $table->decimal('sheet_width_cm', 8, 2)->nullable()->after('unit');
            }
            if (!Schema::hasColumn('materials', 'sheet_height_cm')) {
                $table->decimal('sheet_height_cm', 8, 2)->nullable()->after('sheet_width_cm');
            }
            if (!Schema::hasColumn('materials', 'grain_direction')) {
                $table->string('grain_direction', 20)->nullable()->after('sheet_height_cm');
            }
            if (!Schema::hasColumn('materials', 'kerf_mm')) {
                $table->decimal('kerf_mm', 5, 2)->nullable()->after('grain_direction');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('materials')) {
            return;
        }

        Schema::table('materials', function (Blueprint $table) {
            foreach (['kerf_mm', 'grain_direction', 'sheet_height_cm', 'sheet_width_cm'] as $col) {
                if (Schema::hasColumn('materials', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
