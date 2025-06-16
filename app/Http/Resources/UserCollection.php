<?php
// app/Http/Resources/UserCollection.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->count(),
                'active_users' => $this->where('is_active', true)->count(),
                'inactive_users' => $this->where('is_active', false)->count(),
                'two_factor_enabled' => $this->where('two_factor_enabled', true)->count(),
                'oauth_users' => $this->filter(function ($user) {
                    return $user->oauthProviders->count() > 0;
                })->count(),
            ],
        ];
    }
}