<?php
// app/Http/Resources/PermissionResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'roles' => $this->whenLoaded('roles', function () {
                return RoleResource::collection($this->roles);
            }),
            'roles_count' => $this->when(
                !$this->relationLoaded('roles'),
                function () {
                    return $this->roles()->count();
                }
            ),
            'users_count' => $this->users()->count(),
        ];
    }
}
