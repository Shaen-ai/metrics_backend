<?php

namespace App\Services\Planner\Engine;

use App\Models\User;
use App\Services\Planner\Rules\WardrobeRules;

class WardrobeEngine
{
    public function __construct(private readonly WardrobeRules $rules)
    {
    }

    public function generate(array $intent, User $admin): array
    {
        $dimensions = $this->rules->dimensions($intent);
        $modules = $this->rules->modules($dimensions, $intent);
        $estimatedPrice = $this->rules->estimatePrice($dimensions, $modules, $intent['budget'] ?? null);

        return [
            'furniture_plan' => [
                'room' => $this->room($intent),
                'items' => [[
                    'id' => 'generated-wardrobe',
                    'type' => 'wardrobe',
                    'name' => 'Rule-based wardrobe',
                    'category' => 'Wardrobe',
                    'width_m' => $dimensions['width_m'],
                    'depth_m' => $dimensions['depth_m'],
                    'height_m' => $dimensions['height_m'],
                    'color' => $this->color($intent),
                    'position' => ['x' => 0, 'z' => -1.2],
                    'rotation_y' => 0,
                ]],
            ],
            'modules' => $modules,
            'estimated_price' => $estimatedPrice,
            'warnings' => [],
        ];
    }

    private function room(array $intent): array
    {
        return [
            'width' => $intent['dimensions']['width_m'] ?? 4.0,
            'depth' => $intent['dimensions']['depth_m'] ?? 3.5,
            'height' => $intent['dimensions']['height_m'] ?? 2.8,
        ];
    }

    private function color(array $intent): string
    {
        $colors = array_map('strtolower', $intent['colors'] ?? []);
        $joined = implode(' ', $colors);

        return match (true) {
            str_contains($joined, 'black') => '#2F2F2F',
            str_contains($joined, 'white') => '#F5F1E8',
            str_contains($joined, 'oak'), str_contains($joined, 'wood') => '#C8A46D',
            str_contains($joined, 'gray'), str_contains($joined, 'grey') => '#9CA3AF',
            default => '#BFA58A',
        };
    }
}
