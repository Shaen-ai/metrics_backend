<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode_id' => ['sometimes', 'exists:modes,id'],
            'sub_mode_id' => ['sometimes', 'exists:sub_modes,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'width' => ['sometimes', 'numeric', 'min:0'],
            'height' => ['sometimes', 'numeric', 'min:0'],
            'depth' => ['sometimes', 'numeric', 'min:0'],
            'dimension_unit' => ['sometimes', 'in:cm,inch'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'delivery_days' => ['sometimes', 'integer', 'min:1'],
            'category' => ['sometimes', 'string', 'max:255'],
            'additional_categories' => ['sometimes', 'nullable', 'array', 'max:50'],
            'additional_categories.*' => ['string', 'max:120'],
            'planner_subcategory' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['string'],
            'colors' => ['sometimes', 'array'],
            'colors.*.name' => ['required_with:colors', 'string', 'max:255'],
            'colors.*.hex' => ['required_with:colors', 'string', 'max:7'],
            'model_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'model_job_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model_status' => ['sometimes', 'nullable', 'string', 'in:queued,processing,done,failed'],
            'model_error' => ['sometimes', 'nullable', 'string'],
            'supports_outdoor_cushions' => ['sometimes', 'boolean'],
            'outdoor_cushion_defaults' => ['sometimes', 'nullable', 'array'],
            'is_fabric_customizable' => ['sometimes', 'boolean'],
            'fabric_parts' => ['sometimes', 'nullable', 'array', 'max:6'],
            'fabric_parts.*.id' => ['required_with:fabric_parts', 'string', 'max:50'],
            'fabric_parts.*.name' => ['required_with:fabric_parts', 'string', 'max:100'],
            'fabric_parts.*.allowedMaterialIds' => ['sometimes', 'nullable', 'array'],
            'fabric_parts.*.allowedMaterialIds.*' => ['string'],
            'for_design' => ['sometimes', 'boolean'],
            'surface_texture_width_cm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'surface_texture_height_cm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'surface_item_width_cm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'surface_item_height_cm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'surface_layout_pattern' => ['sometimes', 'nullable', 'string', 'in:aligned,staggered,herringbone'],
        ];
    }
}
