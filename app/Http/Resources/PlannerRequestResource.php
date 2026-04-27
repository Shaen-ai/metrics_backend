<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlannerRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adminId' => $this->admin_id,
            'text' => $this->text,
            'imagePaths' => $this->image_paths ?? [],
            'aiInterpretation' => $this->ai_interpretation,
            'result' => $this->result,
            'estimatedPrice' => (float) $this->estimated_price,
            'status' => $this->status,
            'error' => $this->error,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
