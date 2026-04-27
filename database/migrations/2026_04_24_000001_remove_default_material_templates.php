<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clears global decor catalog templates (e.g. seeded Egger rows).
 * Admins add catalog rows later via your chosen import or seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_templates')) {
            return;
        }

        DB::table('material_templates')->delete();
    }

    public function down(): void
    {
        // Irreversible: catalog content is not restored here.
    }
};
