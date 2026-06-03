<?php

namespace App\Http\Requests;

use App\Rules\NotDisposableEmail;
use Illuminate\Foundation\Http\FormRequest;

class RegisterConsumerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'unique:users,email', new NotDisposableEmail],
            'password' => ['required', 'string', 'min:6'],
            'name' => ['required', 'string', 'max:255'],
            'language' => ['sometimes', 'in:en,ru,hy'],
            'referralCode' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
