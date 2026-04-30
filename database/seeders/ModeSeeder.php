<?php

namespace Database\Seeders;

use App\Models\Mode;
use App\Models\SubMode;
use Illuminate\Database\Seeder;

class ModeSeeder extends Seeder
{
    public function run(): void
    {
        $modes = [
            [
                'id' => 'mode-furniture',
                'name' => 'Furniture',
                'slug' => 'furniture',
                'description' => 'Design and manage traditional furniture pieces',
                'icon' => 'Armchair',
                'subModes' => [
                    ['id' => 'sub-kitchen', 'name' => 'Kitchen', 'slug' => 'kitchen', 'description' => 'Cabinets, islands, pantry units, kitchen tables', 'icon' => 'ChefHat'],
                    ['id' => 'sub-living-room', 'name' => 'Living Room', 'slug' => 'living-room', 'description' => 'TV units, coffee tables, shelving, display cabinets', 'icon' => 'Tv'],
                    ['id' => 'sub-bedroom', 'name' => 'Bedroom', 'slug' => 'bedroom', 'description' => 'Beds, wardrobes, nightstands, dressers, vanities', 'icon' => 'Bed'],
                    ['id' => 'sub-dining-room', 'name' => 'Dining Room', 'slug' => 'dining-room', 'description' => 'Dining tables, chairs, buffets, sideboards', 'icon' => 'UtensilsCrossed'],
                    ['id' => 'sub-office', 'name' => 'Office', 'slug' => 'office', 'description' => 'Desks, office chairs, bookcases, filing cabinets', 'icon' => 'Briefcase'],
                    ['id' => 'sub-outdoor', 'name' => 'Outdoor', 'slug' => 'outdoor', 'description' => 'Garden furniture, patio sets, benches', 'icon' => 'TreePine'],
                ],
            ],
            [
                'id' => 'mode-soft-furniture',
                'name' => 'Soft Furniture',
                'slug' => 'soft-furniture',
                'description' => 'Upholstered and soft furnishing items',
                'icon' => 'Sofa',
                'subModes' => [
                    ['id' => 'sub-sofas', 'name' => 'Sofas & Sectionals', 'slug' => 'sofas-sectionals', 'description' => 'L-shaped, U-shaped, sleeper sofas, loveseats', 'icon' => 'Sofa'],
                    ['id' => 'sub-armchairs', 'name' => 'Armchairs & Recliners', 'slug' => 'armchairs-recliners', 'description' => 'Accent chairs, recliners, rocking chairs', 'icon' => 'Armchair'],
                    ['id' => 'sub-ottomans', 'name' => 'Ottomans & Poufs', 'slug' => 'ottomans-poufs', 'description' => 'Footstools, storage ottomans, floor cushions', 'icon' => 'Square'],
                    ['id' => 'sub-mattresses', 'name' => 'Mattresses', 'slug' => 'mattresses', 'description' => 'Spring, foam, hybrid, adjustable beds', 'icon' => 'Bed'],
                    ['id' => 'sub-headboards', 'name' => 'Headboards', 'slug' => 'headboards', 'description' => 'Upholstered, tufted, panel headboards', 'icon' => 'RectangleHorizontal'],
                ],
            ],
        ];

        foreach ($modes as $modeData) {
            $subModes = $modeData['subModes'];
            unset($modeData['subModes']);

            $mode = Mode::create($modeData);

            foreach ($subModes as $subModeData) {
                SubMode::create([
                    ...$subModeData,
                    'mode_id' => $mode->id,
                ]);
            }
        }
    }
}
