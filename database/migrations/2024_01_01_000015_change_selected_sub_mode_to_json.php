<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['selected_sub_mode_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('selected_sub_mode_ids')->nullable()->after('selected_mode_id');
        });

        // Migrate existing data: wrap the old single value into a JSON array
        DB::table('users')
            ->whereNotNull('selected_sub_mode_id')
            ->eachById(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['selected_sub_mode_ids' => json_encode([$user->selected_sub_mode_id])]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('selected_sub_mode_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('selected_sub_mode_id', 36)->nullable()->after('selected_mode_id');
        });

        // Migrate back: take the first element from the JSON array
        DB::table('users')
            ->whereNotNull('selected_sub_mode_ids')
            ->eachById(function ($user) {
                $ids = json_decode($user->selected_sub_mode_ids, true);
                $firstId = $ids[0] ?? null;
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['selected_sub_mode_id' => $firstId]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('selected_sub_mode_ids');
            $table->foreign('selected_sub_mode_id')->references('id')->on('sub_modes')->nullOnDelete();
        });
    }
};
