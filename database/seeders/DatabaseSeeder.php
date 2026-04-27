<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ModeSeeder::class,
            UserSeeder::class,
            CatalogItemSeeder::class,
            ModuleSeeder::class,
            EggerMaterialTemplateSeeder::class,
            DomusMaterialTemplateSeeder::class,
            KastamonuMaterialTemplateSeeder::class,
            AlvicMaterialTemplateSeeder::class,
            CleafMaterialTemplateSeeder::class,
            AgtMaterialTemplateSeeder::class,
            EvoglossMaterialTemplateSeeder::class,
        ]);
    }
}
