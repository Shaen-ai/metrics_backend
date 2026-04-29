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
            'currency' => ['sometimes', 'string', 'max:10'],
            'delivery_days' => ['sometimes', 'integer', 'min:1'],
            'category' => ['sometimes', 'string', 'max:255'],
            'additional_categories' => ['sometimes', 'nullable', 'array', 'max:50'],
            'additional_categories.*' => ['string', 'max:120'],
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
        ];
    }
}
