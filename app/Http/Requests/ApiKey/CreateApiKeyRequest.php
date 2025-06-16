<?php

// app/Http/Requests/ApiKey/CreateApiKeyRequest.php

namespace App\Http\Requests\ApiKey;

use Illuminate\Foundation\Http\FormRequest;

class CreateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string', 'in:user:read,user:write,user:delete,admin:users,admin:roles,admin:system'],
            'allowed_ips' => ['sometimes', 'array'],
            'allowed_ips.*' => ['ip'],
            'expires_at' => ['sometimes', 'date', 'after:now'],
            'rate_limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'scopes.*.in' => 'One or more selected scopes are invalid',
            'allowed_ips.*.ip' => 'All IP addresses must be valid',
            'expires_at.after' => 'Expiration date must be in the future',
            'rate_limit.min' => 'Rate limit must be at least 1 request per minute',
            'rate_limit.max' => 'Rate limit cannot exceed 1000 requests per minute',
        ];
    }
}