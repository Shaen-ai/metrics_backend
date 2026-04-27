<?php

namespace App\Services\Planner\Rules;

class KitchenRules
{
    public function dimensions(array $intent): array
    {
        $roomWidth = (float) ($intent['dimensions']['width_m'] ?? 0);
        $runLength = $roomWidth > 0 ? max(1.8, min(4.8, $roomWidth - 0.8)) : 3.0;

        return [
            'run_length_m' => round($runLength, 2),
            'base_depth_m' => 0.6,
            'base_height_m' => 0.9,
            'wall_depth_m' => 0.35,
            'wall_height_m' => 0.72,
        ];
    }

    public function modules(array $dimensions, array $intent): array
    {
        $baseCount = max(2, (int) floor($dimensions['run_length_m'] / 0.6));
        $modules = [];

        for ($i = 1; $i <= $baseCount; $i++) {
            $modules[] = [
                'type' => 'kitchen_base',
                'name' => "Base cabinet {$i}",
                'width_m' => 0.6,
                'depth_m' => $dimensions['base_depth_m'],
                'height_m' => $dimensions['base_height_m'],
            ];
        }

        for ($i = 1; $i <= max(1, $baseCount - 1); $i++) {
            $modules[] = [
                'type' => 'kitchen_wall',
                'name' => "Wall cabinet {$i}",
                'width_m' => 0.6,
                'depth_m' => $dimensions['wall_depth_m'],
                'height_m' => $dimensions['wall_height_m'],
            ];
        }

        return $modules;
    }

    public function estimatePrice(array $dimensions, array $modules, ?float $budget): float
    {
        $price = ($dimensions['run_length_m'] * 650) + (count($modules) * 85);

        if ($budget !== null && $budget > 0) {
            $price = min($price, max($budget, $price * 0.75));
        }

        return round($price, 2);
    }
}
