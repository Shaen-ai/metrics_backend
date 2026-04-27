<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'id' => 'mod-1',
                'admin_id' => 'user-1',
                'mode_id' => 'mode-furniture',
                'sub_mode_id' => 'sub-kitchen',
                'name' => 'Base Cabinet Unit',
                'description' => 'Standard 60cm base cabinet with one shelf',
                'width' => 60,
                'height' => 72,
                'depth' => 56,
                'price' => 199,
                'category' => 'base',
                'placement_type' => 'floor',
                'images' => ['https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=400'],
                'connection_points' => [
                    ['position' => 'top', 'type' => 'standard'],
                    ['position' => 'left', 'type' => 'standard'],
                    ['position' => 'right', 'type' => 'standard'],
                ],
                'compatible_with' => ['mod-2', 'mod-3', 'mod-4'],
            ],
            [
                'id' => 'mod-2',
                'admin_id' => 'user-1',
                'mode_id' => 'mode-furniture',
                'sub_mode_id' => 'sub-kitchen',
                'name' => 'Wall Cabinet Unit',
                'description' => '60cm wall-mounted cabinet with glass door option',
                'width' => 60,
                'height' => 70,
                'depth' => 35,
                'price' => 159,
                'category' => 'top',
                'placement_type' => 'wall',
                'images' => ['https://images.unsplash.com/photo-1556909172-8c2f041fca1e?w=400'],
                'connection_points' => [
                    ['position' => 'bottom', 'type' => 'standard'],
                    ['position' => 'left', 'type' => 'standard'],
                    ['position' => 'right', 'type' => 'standard'],
                ],
                'compatible_with' => ['mod-1', 'mod-3'],
            ],
            [
                'id' => 'mod-3',
                'admin_id' => 'user-1',
                'mode_id' => 'mode-furniture',
                'sub_mode_id' => 'sub-kitchen',
                'name' => 'Drawer Unit',
                'description' => 'Base unit with 3 soft-close drawers',
                'width' => 60,
                'height' => 72,
                'depth' => 56,
                'price' => 279,
                'category' => 'drawer',
                'placement_type' => 'floor',
                'images' => ['https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=400'],
                'connection_points' => [
                    ['position' => 'top', 'type' => 'standard'],
                    ['position' => 'left', 'type' => 'standard'],
                    ['position' => 'right', 'type' => 'standard'],
                ],
                'compatible_with' => ['mod-1', 'mod-2', 'mod-4'],
            ],
            [
                'id' => 'mod-4',
                'admin_id' => 'user-1',
                'mode_id' => 'mode-furniture',
                'sub_mode_id' => 'sub-kitchen',
                'name' => 'Corner Unit',
                'description' => 'L-shaped corner base cabinet with rotating shelf',
                'width' => 90,
                'height' => 72,
                'depth' => 90,
                'price' => 349,
                'category' => 'corner',
                'placement_type' => 'floor',
                'images' => ['https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=400'],
                'connection_points' => [
                    ['position' => 'top', 'type' => 'standard'],
                    ['position' => 'left', 'type' => 'standard'],
                    ['position' => 'right', 'type' => 'standard'],
                ],
                'compatible_with' => ['mod-1', 'mod-3'],
            ],
            [
                'id' => 'mod-5',
                'admin_id' => 'user-1',
                'mode_id' => 'mode-furniture',
                'sub_mode_id' => 'sub-living-room',
                'name' => 'Shelf Unit',
                'description' => 'Open shelf unit for living room storage',
                'width' => 80,
                'height' => 180,
                'depth' => 35,
                'price' => 249,
                'category' => 'shelf',
                'placement_type' => 'wall',
                'images' => ['https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=400'],
                'connection_points' => [
                    ['position' => 'left', 'type' => 'standard'],
                    ['position' => 'right', 'type' => 'standard'],
                ],
                'compatible_with' => ['mod-6'],
            ],
            [
                'id' => 'mod-6',
                'admin_id' => 'user-1',
                'mode_id' => 'mode-furniture',
                'sub_mode_id' => 'sub-living-room',
                'name' => 'TV Base Unit',
                'description' => 'Low cabinet for TV and media equipment',
                'width' => 120,
                'height' => 45,
                'depth' => 40,
                'price' => 329,
                'category' => 'base',
                'placement_type' => 'floor',
                'images' => ['https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=400'],
                'connection_points' => [
                    ['position' => 'left', 'type' => 'standard'],
                    ['position' => 'right', 'type' => 'standard'],
                ],
                'compatible_with' => ['mod-5'],
            ],
        ];

        // First pass: create all modules
        foreach ($modules as $moduleData) {
            $images = $moduleData['images'];
            $connectionPoints = $moduleData['connection_points'];
            unset($moduleData['images'], $moduleData['connection_points'], $moduleData['compatible_with']);

            $module = Module::create($moduleData);

            foreach ($images as $i => $url) {
                $module->images()->create(['url' => $url, 'sort_order' => $i]);
            }

            foreach ($connectionPoints as $point) {
                $module->connectionPoints()->create($point);
            }
        }

        // Second pass: set up compatibilities (all modules exist now)
        foreach ($modules as $moduleData) {
            $module = Module::find($moduleData['id']);
            if ($module && !empty($moduleData['compatible_with'])) {
                $module->compatibleModules()->sync($moduleData['compatible_with']);
            }
        }
    }
}
