<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogItemRequest extends FormRequest
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
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'width' => ['required', 'numeric', 'min:0'],
            'height' => ['required', 'numeric', 'min:0'],
            'depth' => ['required', 'numeric', 'min:0'],
            'dimension_unit' => ['sometimes', 'in:cm,inch'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'delivery_days' => ['required', 'integer', 'min:1'],
            'category' => ['required', 'string', 'max:255'],
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
