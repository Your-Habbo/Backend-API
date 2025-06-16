<?php

// app/Http/Resources/PermissionCollection.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PermissionCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->count(),
                'categories' => $this->groupBy(function ($permission) {
                    return explode(' ', $permission->name)[0];
                })->map->count(),
            ],
        ];
    }
}