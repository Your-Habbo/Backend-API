<?php
// app/Http/Resources/RoleResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'permissions' => $this->whenLoaded('permissions', function () {
                return PermissionResource::collection($this->permissions);
            }),
            'permissions_count' => $this->when(
                !$this->relationLoaded('permissions'),
                function () {
                    return $this->permissions()->count();
                }
            ),
            'users_count' => $this->users()->count(),
        ];
    }
}
