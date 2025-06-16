<?php
// app/Http/Requests/Admin/CreateUserRequest.php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create users');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'password' => ['required', 'string', Password::defaults()],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'is_active' => ['sometimes', 'boolean'],
            'email_verified' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username can only contain letters, numbers, dots, underscores, and hyphens',
            'roles.*.exists' => 'One or more selected roles do not exist',
        ];
    }
}