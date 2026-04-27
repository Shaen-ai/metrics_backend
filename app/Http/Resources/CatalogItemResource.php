<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adminId' => $this->admin_id,
            'modeId' => $this->mode_id,
            'subModeId' => $this->sub_mode_id,
            'name' => $this->name,
            'model' => $this->model,
            'description' => $this->description,
            'images' => $this->whenLoaded('images', fn () =>
                $this->images->pluck('url')->toArray()
            ),
            'sizes' => [
                'width' => (float) $this->width,
                'height' => (float) $this->height,
                'depth' => (float) $this->depth,
                'unit' => $this->dimension_unit,
            ],
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'deliveryDays' => $this->delivery_days,
            'category' => $this->category,
            'availableColors' => $this->whenLoaded('colors', fn () =>
                $this->colors->map(fn ($c) => ['name' => $c->name, 'hex' => $c->hex])->toArray()
            ),
            'modelUrl' => $this->model_url,
            'modelJobId' => $this->model_job_id,
            'modelStatus' => $this->model_status,
            'modelError' => $this->model_error,
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
