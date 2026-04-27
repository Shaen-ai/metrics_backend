<?php

namespace App\Services\Planner\Rules;

class WardrobeRules
{
    public function dimensions(array $intent): array
    {
        $roomWidth = (float) ($intent['dimensions']['width_m'] ?? 0);
        $maxWidth = $roomWidth > 0 ? max(1.2, min(3.6, $roomWidth - 0.6)) : 2.4;

        return [
            'width_m' => round($maxWidth, 2),
            'depth_m' => 0.6,
            'height_m' => $this->heightFromIntent($intent),
            'sections' => $maxWidth >= 2.4 ? 3 : 2,
        ];
    }

    public function modules(array $dimensions, array $intent): array
    {
        $sections = (int) $dimensions['sections'];
        $sectionWidth = round($dimensions['width_m'] / $sections, 2);
        $layout = $this->interiorLayout($intent);
        $modules = [];

        for ($i = 1; $i <= $sections; $i++) {
            $modules[] = [
                'type' => 'wardrobe_section',
                'name' => "Wardrobe section {$i}",
                'width_m' => $sectionWidth,
                'depth_m' => $dimensions['depth_m'],
                'height_m' => $dimensions['height_m'],
                'layout' => $layout,
            ];
        }

        $modules[] = [
            'type' => 'wardrobe_doors',
            'name' => $sections >= 3 ? 'Sliding door set' : 'Hinged door set',
            'quantity' => $sections >= 3 ? 2 : $sections,
        ];

        return $modules;
    }

    public function estimatePrice(array $dimensions, array $modules, ?float $budget): float
    {
        $surface = ($dimensions['width_m'] * $dimensions['height_m'] * 2)
            + ($dimensions['depth_m'] * $dimensions['height_m'] * 2)
            + ($dimensions['width_m'] * $dimensions['depth_m']);

        $price = ($surface * 95) + (count($modules) * 45) + 220;

        if ($budget !== null && $budget > 0) {
            $price = min($price, max($budget, $price * 0.75));
        }

        return round($price, 2);
    }

    private function heightFromIntent(array $intent): float
    {
        $height = (float) ($intent['dimensions']['height_m'] ?? 0);
        if ($height > 0) {
            return round(max(1.8, min(2.6, $height - 0.15)), 2);
        }

        return 2.4;
    }

    private function interiorLayout(array $intent): string
    {
        $text = strtolower(implode(' ', [
            $intent['notes'] ?? '',
            ...($intent['requirements'] ?? []),
        ]));

        if (str_contains($text, 'shoe') || str_contains($text, 'shelf')) {
            return 'shelves';
        }

        if (str_contains($text, 'coat') || str_contains($text, 'hang')) {
            return 'hanging';
        }

        return 'mixed';
    }
}
