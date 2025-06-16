<?php

// app/Http/Resources/OAuthProviderResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OAuthProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'is_active' => $this->is_active,
            'config' => $this->when(
                $request->user()?->can('manage oauth providers'),
                $this->config
            ),
            'users_count' => $this->userOAuthProviders()->count(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}