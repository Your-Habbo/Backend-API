<?php
// app/Http/Requests/User/UpdateProfileRequest.php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes', 
                'email', 
                'max:255', 
                Rule::unique('users', 'email')->ignore($user->id)
            ],
            'username' => [
                'sometimes', 
                'string', 
                'max:255', 
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user->id)
            ],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['sometimes', 'string', 'confirmed', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username can only contain letters, numbers, dots, underscores, and hyphens',
            'current_password.required_with' => 'Current password is required when changing password',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('password') && $this->filled('current_password')) {
                if (!password_verify($this->current_password, $this->user()->password)) {
                    $validator->errors()->add('current_password', 'The current password is incorrect.');
                }
            }
        });
    }
}