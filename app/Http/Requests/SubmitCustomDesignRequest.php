<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCustomDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'design' => ['required', 'array'],
            'snapshot' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'room_name' => ['nullable', 'string', 'max:160'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $encoded = json_encode($this->input('design'));
            if ($encoded === false || strlen($encoded) > 900000) {
                $validator->errors()->add('design', 'Design data is too large to submit.');
            }

            $snapshot = $this->input('snapshot');
            if (is_string($snapshot) && strlen($snapshot) > 2500000) {
                $validator->errors()->add('snapshot', 'Snapshot image is too large.');
            }
        });
    }
}
