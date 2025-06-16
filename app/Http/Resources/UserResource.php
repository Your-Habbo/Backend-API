<?php
// app/Http/Resources/UserResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'two_factor_enabled' => $this->two_factor_enabled,
            'two_factor_confirmed_at' => $this->two_factor_confirmed_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'last_login_ip' => $this->last_login_ip,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Conditional fields
            'roles' => $this->whenLoaded('roles', function () {
                return RoleResource::collection($this->roles);
            }),
            'permissions' => $this->when(
                $request->user()?->can('view permissions') || $request->user()?->id === $this->id,
                function () {
                    return $this->getAllPermissions()->pluck('name');
                }
            ),
            'oauth_providers' => $this->whenLoaded('oauthProviders', function () {
                return $this->oauthProviders->map(function ($provider) {
                    return [
                        'provider' => $provider->oauthProvider->name,
                        'provider_username' => $provider->provider_username,
                        'provider_email' => $provider->provider_email,
                        'linked_at' => $provider->created_at->toISOString(),
                        'last_used_at' => $provider->last_used_at?->toISOString(),
                    ];
                });
            }),
            'api_keys_count' => $this->when(
                $request->user()?->can('view api keys') || $request->user()?->id === $this->id,
                function () {
                    return $this->apiKeys()->active()->count();
                }
            ),
            'login_history' => $this->when(
                $request->user()?->can('view users') || $request->user()?->id === $this->id,
                $this->login_history
            ),
            'security_settings' => $this->when(
                $request->user()?->id === $this->id,
                $this->security_settings
            ),
        ];
    }
}
