<?php

namespace Database\Seeders;

use App\Models\CatalogItem;
use Illuminate\Database\Seeder;

class CatalogItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'id' => 'cat-1',
                'admin_id' => 'user-1',
                'mode_id' => 'mode-furniture',
                'sub_mode_id' => 'sub-kitchen',
                'name' => 'Modern Oak Kitchen Cabinet',
                'description' => 'Premium oak wood kitchen cabinet with soft-close hinges and adjustable shelves. Perfect for modern kitchen designs.',
                'width' => 80,
                'height' => 90,
                'depth' => 60,
                'price' => 899,
                'currency' => 'USD',
                'delivery_days' => 14,
                'category' => 'Cabinets',
                'images' => [
                    'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=800',
                    'https://images.unsplash.com/photo-1556909172-8c2f041fca1e?w=800',
                ],
            ],
            [
                'id' => 'cat-2',
                'admin_id' => 'user-1',
                'mode_id' => 'mode-furniture',
                'sub_mode_id' => 'sub-living-room',
                'name' => 'Minimalist TV Unit',
                'description' => 'Sleek wall-mounted TV unit with hidden cable management and LED lighting option.',
                'width' => 180,
                'height' => 45,
                'depth' => 40,
                'price' => 649,
                'currency' => 'USD',
                'delivery_days' => 10,
                'category' => 'TV Units',
                'images' => [
                    'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800',
                ],
            ],
            [
                'id' => 'cat-3',
                'admin_id' => 'user-1',
                'mode_id' => 'mode-furniture',
                'sub_mode_id' => 'sub-bedroom',
                'name' => 'King Size Platform Bed',
                'description' => 'Contemporary platform bed with integrated headboard and under-bed storage drawers.',
                'width' => 200,
                'height' => 110,
                'depth' => 220,
                'price' => 1299,
                'currency' => 'USD',
                'delivery_days' => 21,
                'category' => 'Beds',
                'images' => [
                    'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800',
                ],
            ],
            [
                'id' => 'cat-4',
                'admin_id' => 'user-1',
                'mode_id' => 'mode-furniture',
                'sub_mode_id' => 'sub-dining-room',
                'name' => 'Extendable Dining Table',
                'description' => 'Elegant dining table that extends from 160cm to 220cm. Seats 6-10 people comfortably.',
                'width' => 160,
                'height' => 75,
                'depth' => 90,
                'price' => 999,
                'currency' => 'USD',
                'delivery_days' => 14,
                'category' => 'Tables',
                'images' => [
                    'https://images.unsplash.com/photo-1617806118233-18e1de247200?w=800',
                ],
            ],
            [
                'id' => 'cat-5',
                'admin_id' => 'user-1',
                'mode_id' => 'mode-furniture',
                'sub_mode_id' => 'sub-office',
                'name' => 'Executive Desk',
                'description' => 'Large executive desk with built-in cable management, drawers, and premium walnut finish.',
                'width' => 180,
                'height' => 76,
                'depth' => 80,
                'price' => 1499,
                'currency' => 'USD',
                'delivery_days' => 18,
                'category' => 'Desks',
                'images' => [
                    'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=800',
                ],
            ],
        ];

        foreach ($items as $itemData) {
            $images = $itemData['images'];
            unset($itemData['images']);

            $item = CatalogItem::create($itemData);

            foreach ($images as $i => $url) {
                $item->images()->create(['url' => $url, 'sort_order' => $i]);
            }
        }
    }
}
