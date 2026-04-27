<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        // Irreversible: default catalog rows are no longer shipped via seeder.
    }
};
