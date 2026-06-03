<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scraped_products')) {
            return;
        }

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Լամինատե Հատակներ')
            ->update([
                'product_family' => 'flooring',
                'product_subtype' => 'laminate',
                'category_en' => 'Laminate Flooring',
                'embedding_text' => null,
            ]);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Փայտե Հատակներ')
            ->update([
                'product_family' => 'flooring',
                'product_subtype' => 'parquet',
                'category_en' => 'Wooden Flooring',
                'embedding_text' => null,
            ]);

        // Backfill category_en for all Domus categories
        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Լամինատե Հատակներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Laminate Flooring']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Փայտե Հատակներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Wooden Flooring']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Փայտե Աթոռներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Wooden Chairs']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', '.Բազկաթոռներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Armchairs']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Գրասենյակային Աթոռներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Office Chairs']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Մետաղական Ոտքերով Աթոռներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Metal Leg Chairs']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Պլաստմասե Աթոռներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Plastic Chairs']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Բարային Աթոռներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Bar Stools']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Պուֆ Աթոռներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Pouf Chairs']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Ճոճաթոռներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Rocking Chairs']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Սեղաններ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Tables']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Սեղան/Աթոռ Հավաքածուներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Table/Chair Sets']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Փափուկ Կահույք')
            ->whereNull('category_en')
            ->update(['category_en' => 'Upholstered Furniture']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Ննջասենյակի Կահույք')
            ->whereNull('category_en')
            ->update(['category_en' => 'Bedroom Furniture']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Հյուրասենյակի Կահույք')
            ->whereNull('category_en')
            ->update(['category_en' => 'Living Room Furniture']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Դարակաշարեր')
            ->whereNull('category_en')
            ->update(['category_en' => 'Shelving Units']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Մահճակալներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Beds']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Հիմքեր')
            ->whereNull('category_en')
            ->update(['category_en' => 'Bed Bases']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Ջահեր')
            ->whereNull('category_en')
            ->update(['category_en' => 'Chandeliers']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Լուսամփոփներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Light Fixtures']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Սեղանի Լուսատուներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Table Lamps']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Կետային Լուսատուներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Spotlights']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Ճաղաշարքային Համակարգ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Track Lighting System']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Պաստառներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Wallpapers']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Ամառանոցային Աթոռներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Outdoor Chairs']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Ամառանոցային Կահույքի Հավաքածուներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Outdoor Furniture Sets']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Կախիչներ')
            ->whereNull('category_en')
            ->update(['category_en' => 'Coat Racks']);

        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->where('category', 'Դեկորատիվ Բարձեր')
            ->whereNull('category_en')
            ->update(['category_en' => 'Decorative Pillows']);
    }

    public function down(): void
    {
        DB::table('scraped_products')
            ->where('source_marketplace', 'domus')
            ->whereIn('category', ['Լամինատե Հատակներ', 'Փայտե Հատակներ'])
            ->update([
                'product_family' => null,
                'product_subtype' => null,
                'category_en' => null,
            ]);
    }
};
