<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $orders = [
            [
                'id' => 'order-1',
                'admin_id' => 'user-1',
                'customer_name' => 'Alice Johnson',
                'customer_email' => 'alice@example.com',
                'customer_phone' => '+1 555-0101',
                'customer_address' => '123 Main St, Suite 4, Springfield',
                'type' => 'catalog',
                'total_price' => 4895,
                'status' => 'pending',
                'items' => [
                    [
                        'item_type' => 'catalog',
                        'item_id' => 'cat-1',
                        'name' => 'Modern Oak Kitchen Cabinet',
                        'quantity' => 4,
                        'price' => 899,
                    ],
                    [
                        'item_type' => 'catalog',
                        'item_id' => 'cat-3',
                        'name' => 'King Size Platform Bed',
                        'quantity' => 1,
                        'price' => 1299,
                    ],
                ],
            ],
            [
                'id' => 'order-2',
                'admin_id' => 'user-1',
                'customer_name' => 'Bob Smith',
                'customer_email' => 'bob@example.com',
                'customer_phone' => '+1 555-0202',
                'customer_address' => '456 Oak Ave, Apartment 12B',
                'type' => 'module',
                'total_price' => 1155,
                'status' => 'reviewed',
                'notes' => 'Customer requested oak finish',
                'items' => [
                    [
                        'item_type' => 'module',
                        'item_id' => 'mod-1',
                        'name' => 'Base Cabinet Unit',
                        'quantity' => 3,
                        'price' => 199,
                    ],
                    [
                        'item_type' => 'module',
                        'item_id' => 'mod-3',
                        'name' => 'Drawer Unit',
                        'quantity' => 2,
                        'price' => 279,
                    ],
                ],
            ],
        ];

        foreach ($orders as $orderData) {
            $items = $orderData['items'];
            unset($orderData['items']);

            $order = Order::create($orderData);

            foreach ($items as $item) {
                $order->items()->create($item);
            }
        }
    }
}
