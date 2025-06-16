<?php
// app/Http/Requests/Auth/TwoFactorRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:6', 'max:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Authentication code is required',
            'code.min' => 'Authentication code must be at least 6 characters',
            'code.max' => 'Authentication code must be at most 8 characters',
        ];
    }
}
