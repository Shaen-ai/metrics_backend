<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_templates', function (Blueprint $table) {
            $table->json('types')->nullable()->after('type');
        });

        DB::table('material_templates')->orderBy('id')->chunk(100, function ($rows) {
            foreach ($rows as $row) {
                $t = $row->type;
                if ($t === null || $t === '') {
                    continue;
                }
                DB::table('material_templates')
                    ->where('id', $row->id)
                    ->update(['types' => json_encode([$t])]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_templates', function (Blueprint $table) {
            $table->dropColumn('types');
        });
    }
};
