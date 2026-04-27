<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->json('types')->nullable()->after('type');
        });

        $rows = DB::table('materials')->select('id', 'type')->get();
        foreach ($rows as $row) {
            if ($row->type === null || $row->type === '') {
                continue;
            }
            DB::table('materials')->where('id', $row->id)->update([
                'types' => json_encode([$row->type]),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('types');
        });
    }
};
