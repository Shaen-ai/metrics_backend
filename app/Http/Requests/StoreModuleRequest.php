<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode_id' => ['required', 'exists:modes,id'],
            'sub_mode_id' => ['required', 'exists:sub_modes,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'width' => ['required', 'numeric', 'min:0'],
            'height' => ['required', 'numeric', 'min:0'],
            'depth' => ['required', 'numeric', 'min:0'],
            'dimension_unit' => ['sometimes', 'in:cm,inch'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'image_url' => ['sometimes', 'nullable', 'string'],
            'category' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['string', 'url'],
            'connection_points' => ['sometimes', 'array'],
            'connection_points.*.position' => ['required_with:connection_points', 'in:top,bottom,left,right,front,back'],
            'connection_points.*.type' => ['sometimes', 'string', 'max:50'],
            'compatible_with' => ['sometimes', 'array'],
            'compatible_with.*' => ['string', 'exists:modules,id'],
            'model_url' => ['sometimes', 'nullable', 'string'],
            'model_job_id' => ['sometimes', 'nullable', 'string'],
            'model_status' => ['sometimes', 'nullable', 'in:queued,processing,done,failed'],
            'model_error' => ['sometimes', 'nullable', 'string'],
            'placement_type' => ['sometimes', 'in:floor,wall'],
            'is_configurable_template' => ['sometimes', 'boolean'],
            'pricing_body_weight' => ['sometimes', 'numeric', 'min:0'],
            'pricing_door_weight' => ['sometimes', 'numeric', 'min:0'],
            'default_handle_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'default_cabinet_material_id' => [
                'sometimes', 'nullable', 'string',
                Rule::exists('materials', 'id')->where('admin_id', $this->user()?->id),
            ],
            'default_door_material_id' => [
                'sometimes', 'nullable', 'string',
                Rule::exists('materials', 'id')->where('admin_id', $this->user()?->id),
            ],
            'template_options' => ['sometimes', 'nullable', 'array'],
            'template_options.*.id' => ['required_with:template_options', 'string', 'max:64'],
            'template_options.*.label' => ['required_with:template_options', 'string', 'max:255'],
            'template_options.*.priceDelta' => ['required_with:template_options', 'numeric'],
            'template_options.*.defaultSelected' => ['sometimes', 'boolean'],
            'allowed_handle_ids' => ['sometimes', 'nullable', 'array'],
            'allowed_handle_ids.*' => ['string', 'max:64'],
        ];
    }
}
