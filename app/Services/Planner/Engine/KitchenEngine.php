<?php

namespace App\Services\Planner\Engine;

use App\Models\User;
use App\Services\Planner\Rules\KitchenRules;

class KitchenEngine
{
    public function __construct(private readonly KitchenRules $rules)
    {
    }

    public function generate(array $intent, User $admin): array
    {
        $dimensions = $this->rules->dimensions($intent);
        $modules = $this->rules->modules($dimensions, $intent);
        $estimatedPrice = $this->rules->estimatePrice($dimensions, $modules, $intent['budget'] ?? null);

        $items = [];
        $x = -($dimensions['run_length_m'] / 2) + 0.3;
        foreach ($modules as $idx => $module) {
            if ($module['type'] !== 'kitchen_base') {
                continue;
            }

            $items[] = [
                'id' => 'generated-kitchen-base-'.$idx,
                'type' => 'kitchen_base',
                'name' => $module['name'],
                'category' => 'Kitchen',
                'width_m' => $module['width_m'],
                'depth_m' => $module['depth_m'],
                'height_m' => $module['height_m'],
                'color' => '#D8C7B1',
                'position' => ['x' => round($x, 2), 'z' => -1.2],
                'rotation_y' => 0,
            ];
            $x += 0.6;
        }

        return [
            'furniture_plan' => [
                'room' => [
                    'width' => $intent['dimensions']['width_m'] ?? 4.5,
                    'depth' => $intent['dimensions']['depth_m'] ?? 3.5,
                    'height' => $intent['dimensions']['height_m'] ?? 2.8,
                ],
                'items' => $items,
            ],
            'modules' => $modules,
            'estimated_price' => $estimatedPrice,
            'warnings' => [],
        ];
    }
}
