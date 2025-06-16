<?php
// app/Http/Requests/Auth/RegisterRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username can only contain letters, numbers, dots, underscores, and hyphens',
            'terms.accepted' => 'You must accept the terms and conditions',
        ];
    }
}