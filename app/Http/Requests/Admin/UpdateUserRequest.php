<?php
// app/Http/Requests/Admin/UpdateUserRequest.php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit users');
    }

    public function rules(): array
    {
        $userId = $this->route('user');
        
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes', 
                'email', 
                'max:255', 
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'username' => [
                'sometimes', 
                'string', 
                'max:255', 
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($userId)
            ],
            'password' => ['sometimes', 'string', Password::defaults()],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'is_active' => ['sometimes', 'boolean'],
            'email_verified' => ['sometimes', 'boolean'],
            'two_factor_enabled' => ['sometimes', 'boolean'],
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