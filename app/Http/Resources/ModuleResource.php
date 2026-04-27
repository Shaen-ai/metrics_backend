<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adminId' => $this->admin_id,
            'modeId' => $this->mode_id,
            'subModeId' => $this->sub_mode_id,
            'name' => $this->name,
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
            'imageUrl' => $this->image_url,
            'category' => $this->category,
            'connectionPoints' => $this->whenLoaded('connectionPoints', fn () =>
                $this->connectionPoints->map(fn ($cp) => [
                    'position' => $cp->position,
                    'type' => $cp->type,
                ])->toArray()
            ),
            'compatibleWith' => $this->whenLoaded('compatibleModules', fn () =>
                $this->compatibleModules->pluck('id')->toArray()
            ),
            'isActive' => $this->is_active,
            'modelUrl' => $this->model_url,
            'modelJobId' => $this->model_job_id,
            'modelStatus' => $this->model_status,
            'modelError' => $this->model_error,
            'placementType' => $this->placement_type ?? 'floor',
            'isConfigurableTemplate' => (bool) $this->is_configurable_template,
            'pricingBodyWeight' => $this->pricing_body_weight !== null
                ? (float) $this->pricing_body_weight
                : 1.0,
            'pricingDoorWeight' => $this->pricing_door_weight !== null
                ? (float) $this->pricing_door_weight
                : 1.0,
            'defaultCabinetMaterialId' => $this->default_cabinet_material_id,
            'defaultDoorMaterialId' => $this->default_door_material_id,
            'defaultHandleId' => $this->default_handle_id,
            'templateOptions' => $this->template_options,
            'allowedHandleIds' => $this->allowed_handle_ids,
        ];
    }
}
