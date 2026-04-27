<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->char('default_cabinet_material_id', 36)->nullable()->after('placement_type');
            $table->char('default_door_material_id', 36)->nullable()->after('default_cabinet_material_id');
            $table->decimal('pricing_body_weight', 10, 4)->default(1)->after('default_door_material_id');
            $table->decimal('pricing_door_weight', 10, 4)->default(1)->after('pricing_body_weight');
            $table->string('default_handle_id', 64)->nullable()->after('pricing_door_weight');
            $table->json('template_options')->nullable()->after('default_handle_id');
            $table->json('allowed_handle_ids')->nullable()->after('template_options');
            $table->boolean('is_configurable_template')->default(false)->after('allowed_handle_ids');

            $table->foreign('default_cabinet_material_id')->references('id')->on('materials')->nullOnDelete();
            $table->foreign('default_door_material_id')->references('id')->on('materials')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropForeign(['default_cabinet_material_id']);
            $table->dropForeign(['default_door_material_id']);
            $table->dropColumn([
                'default_cabinet_material_id',
                'default_door_material_id',
                'pricing_body_weight',
                'pricing_door_weight',
                'default_handle_id',
                'template_options',
                'allowed_handle_ids',
                'is_configurable_template',
            ]);
        });
    }
};
