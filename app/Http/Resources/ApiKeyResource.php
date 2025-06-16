<?php
// app/Http/Resources/ApiKeyResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key_prefix' => $this->key_prefix,
            'scopes' => $this->scopes,
            'allowed_ips' => $this->allowed_ips,
            'last_used_at' => $this->last_used_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'is_active' => $this->is_active,
            'usage_count' => $this->usage_count,
            'rate_limit' => $this->rate_limit,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'is_expired' => $this->isExpired(),
            'days_until_expiration' => $this->expires_at ? 
                max(0, now()->diffInDays($this->expires_at, false)) : null,
        ];
    }
}
