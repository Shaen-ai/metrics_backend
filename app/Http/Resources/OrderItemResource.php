<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'itemType' => $this->item_type,
            'itemId' => $this->item_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => (float) $this->price,
            'selectedMaterials' => $this->whenLoaded('materials', fn () =>
                $this->materials->pluck('id')->toArray()
            ),
            'customData' => $this->custom_data,
        ];
    }
}
