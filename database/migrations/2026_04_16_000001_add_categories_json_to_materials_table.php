<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('materials')) {
            return;
        }

        if (!Schema::hasColumn('materials', 'categories')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->json('categories')->nullable()->after('category');
            });
        }

        foreach (DB::table('materials')->select('id', 'category', 'categories')->get() as $row) {
            $existing = $row->categories;
            $list = is_string($existing) ? json_decode($existing, true) : $existing;
            if (is_array($list) && count($list) > 0) {
                continue;
            }
            DB::table('materials')->where('id', $row->id)->update([
                'categories' => json_encode([$row->category]),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('materials') && Schema::hasColumn('materials', 'categories')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->dropColumn('categories');
            });
        }
    }
};
