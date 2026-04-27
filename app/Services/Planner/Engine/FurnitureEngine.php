<?php

namespace App\Services\Planner\Engine;

use App\Services\Planner\DTO\PlanRequestDTO;
use App\Services\Planner\DTO\PlanResultDTO;

class FurnitureEngine
{
    public function __construct(
        private readonly WardrobeEngine $wardrobeEngine,
        private readonly KitchenEngine $kitchenEngine,
    ) {
    }

    public function generate(PlanRequestDTO $request, array $intent): PlanResultDTO
    {
        $generated = match ($intent['product_type'] ?? 'general') {
            'kitchen' => $this->kitchenEngine->generate($intent, $request->admin),
            default => $this->wardrobeEngine->generate($intent, $request->admin),
        };

        return new PlanResultDTO(
            intent: $intent,
            furniturePlan: $generated['furniture_plan'],
            modules: $generated['modules'],
            estimatedPrice: (float) $generated['estimated_price'],
            warnings: $generated['warnings'] ?? [],
        );
    }
}
