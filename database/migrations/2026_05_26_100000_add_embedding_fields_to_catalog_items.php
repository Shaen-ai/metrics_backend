<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->string('product_family', 64)->nullable()->after('planner_subcategory');
            $table->string('product_subtype', 64)->nullable()->after('product_family');
            $table->json('material_tags')->nullable()->after('product_subtype');
            $table->json('color_tags')->nullable()->after('material_tags');
            $table->json('ai_tags')->nullable()->after('color_tags');
            $table->timestamp('ai_enriched_at')->nullable()->after('ai_tags');
            $table->text('embedding_text')->nullable()->after('ai_enriched_at');
            $table->string('embedding_text_version', 16)->nullable()->after('embedding_text');
            $table->timestamp('embedded_at')->nullable()->after('embedding_text_version');

            $table->index('product_family');
            $table->index('product_subtype');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropIndex(['product_family']);
            $table->dropIndex(['product_subtype']);
            $table->dropColumn([
                'product_family',
                'product_subtype',
                'material_tags',
                'color_tags',
                'ai_tags',
                'ai_enriched_at',
                'embedding_text',
                'embedding_text_version',
                'embedded_at',
            ]);
        });
    }
};
