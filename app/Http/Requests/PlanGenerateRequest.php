<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class PlanGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'admin_slug' => ['required_without:slug', 'string', 'max:255'],
            'slug' => ['required_without:admin_slug', 'string', 'max:255'],
            'text' => ['required', 'string', 'max:4000'],
            'room_image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:6144'],
            'inspiration_image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:6144'],
        ];
    }

    public function plannerAdmin(): User
    {
        $slug = (string) ($this->input('admin_slug') ?: $this->input('slug'));
        $admin = User::where('slug', $slug)->first();

        if (! $admin) {
            throw ValidationException::withMessages([
                'admin_slug' => ['Storefront not found.'],
            ]);
        }

        return $admin;
    }
}
