<?php
// app/Traits/HasApiKeys.php

namespace App\Traits;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasApiKeys
{
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function createApiKey(string $name, array $scopes = [], array $options = []): array
    {
        $keyData = ApiKey::generateKey();
        
        $apiKey = $this->apiKeys()->create([
            'name' => $name,
            'key_hash' => $keyData['hash'],
            'key_prefix' => $keyData['prefix'],
            'scopes' => $scopes,
            'allowed_ips' => $options['allowed_ips'] ?? null,
            'expires_at' => $options['expires_at'] ?? null,
            'rate_limit' => $options['rate_limit'] ?? null,
        ]);

        // Log security event
        $this->logSecurityEvent('api_key_created', [
            'api_key_id' => $apiKey->id,
            'api_key_name' => $name,
            'scopes' => $scopes,
        ]);

        return [
            'api_key' => $apiKey,
            'key' => $keyData['key'], // Only return this once
        ];
    }

    public function revokeApiKey(int $apiKeyId): bool
    {
        $apiKey = $this->apiKeys()->find($apiKeyId);
        
        if (!$apiKey) {
            return false;
        }

        $deleted = $apiKey->delete();

        if ($deleted) {
            $this->logSecurityEvent('api_key_revoked', [
                'api_key_id' => $apiKeyId,
                'api_key_name' => $apiKey->name,
            ]);
        }

        return $deleted;
    }

    public function getActiveApiKeys()
    {
        return $this->apiKeys()->active()->get();
    }

    public function hasApiKeyWithScope(string $scope): bool
    {
        return $this->apiKeys()
            ->active()
            ->where('scopes', 'like', '%"' . $scope . '"%')
            ->exists();
    }
}