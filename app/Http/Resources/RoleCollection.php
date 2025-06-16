<?php

// app/Http/Resources/RoleCollection.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class RoleCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->count(),
                'system_roles' => $this->whereIn('name', [
                    'super-admin', 'admin', 'moderator', 'user', 'guest'
                ])->count(),
                'custom_roles' => $this->whereNotIn('name', [
                    'super-admin', 'admin', 'moderator', 'user', 'guest'
                ])->count(),
            ],
        ];
    }
}
