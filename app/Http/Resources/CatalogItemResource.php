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
            'images' => $this->whenLoaded('images', fn () => $this->images->pluck('url')->toArray()
            ),
            'sizes' => [
                'width' => (float) $this->width,
                'height' => (float) $this->height,
                'depth' => (float) $this->depth,
                'unit' => $this->dimension_unit,
            ],
            'price' => (float) $this->price,
            'unit' => $this->unit,
            'currency' => $this->currency,
            'deliveryDays' => $this->delivery_days,
            'category' => $this->category,
            'plannerSubcategory' => $this->planner_subcategory,
            'additionalCategories' => $this->additional_categories ?? [],
            'allCategories' => $this->mergedCategoryLabels(),
            'availableColors' => $this->whenLoaded('colors', fn () => $this->colors->map(fn ($c) => ['name' => $c->name, 'hex' => $c->hex])->toArray()
            ),
            'modelUrl' => $this->model_url,
            'modelJobId' => $this->model_job_id,
            'modelStatus' => $this->model_status,
            'modelError' => $this->model_error,
            'supportsOutdoorCushions' => (bool) ($this->supports_outdoor_cushions ?? false),
            'outdoorCushionDefaults' => $this->outdoor_cushion_defaults,
            'isFabricCustomizable' => (bool) ($this->is_fabric_customizable ?? false),
            'fabricParts' => $this->fabric_parts ?? [],
            'isActive' => $this->is_active,
            'forDesign' => (bool) ($this->for_design ?? false),
            'surfaceTextureWidthCm' => $this->surface_texture_width_cm !== null ? (float) $this->surface_texture_width_cm : null,
            'surfaceTextureHeightCm' => $this->surface_texture_height_cm !== null ? (float) $this->surface_texture_height_cm : null,
            'surfaceItemWidthCm' => $this->surface_item_width_cm !== null ? (float) $this->surface_item_width_cm : null,
            'surfaceItemHeightCm' => $this->surface_item_height_cm !== null ? (float) $this->surface_item_height_cm : null,
            'surfaceLayoutPattern' => $this->surface_layout_pattern,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
