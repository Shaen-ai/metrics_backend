<?php

namespace App\Services\Planner\DTO;

readonly class PlanResultDTO
{
    public function __construct(
        public array $intent,
        public array $furniturePlan,
        public array $modules,
        public float $estimatedPrice,
        public array $warnings = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'furniture_plan' => $this->furniturePlan,
            'modules' => $this->modules,
            'estimated_price' => round($this->estimatedPrice, 2),
            'warnings' => $this->warnings,
        ];
    }
}
