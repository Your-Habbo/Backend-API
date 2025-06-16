<?php
// app/Services/ApiKeyService.php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class ApiKeyService
{
    public function validateApiKey(string $key, Request $request): ?User
    {
        $prefix = substr($key, 0, 10);
        
        $apiKey = ApiKey::where('key_prefix', $prefix)
            ->active()
            ->first();

        if (!$apiKey || !$apiKey->validateKey($key)) {
            return null;
        }

        // Check IP restrictions
        if (!$apiKey->isIpAllowed($request->ip())) {
            return null;
        }

        // Check rate limiting
        if ($apiKey->rate_limit && !$this->checkRateLimit($apiKey, $request)) {
            return null;
        }

        // Update usage
        $apiKey->incrementUsage();

        return $apiKey->user;
    }

    public function createApiKey(User $user, string $name, array $scopes = [], array $options = []): array
    {
        if ($user->apiKeys()->count() >= 10) {
            throw new \Exception('Maximum number of API keys reached (10).');
        }

        return $user->createApiKey($name, $scopes, $options);
    }

    public function revokeApiKey(User $user, int $apiKeyId): bool
    {
        return $user->revokeApiKey($apiKeyId);
    }

    public function getUserApiKeys(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->getActiveApiKeys();
    }

    protected function checkRateLimit(ApiKey $apiKey, Request $request): bool
    {
        $key = "api_key_rate_limit:{$apiKey->id}:{$request->ip()}";
        
        return RateLimiter::attempt(
            $key,
            $apiKey->rate_limit,
            function () {
                // Allow the request
            },
            60 // 1 minute window
        );
    }

    public function getAvailableScopes(): array
    {
        return [
            'user:read' => 'Read user information',
            'user:write' => 'Update user information',
            'user:delete' => 'Delete user account',
            'admin:users' => 'Manage all users',
            'admin:roles' => 'Manage roles and permissions',
            'admin:system' => 'System administration',
        ];
    }
}
