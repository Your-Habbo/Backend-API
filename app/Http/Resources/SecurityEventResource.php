<?php
// app/Http/Resources/SecurityEventResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecurityEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'event_data' => $this->event_data,
            'risk_level' => $this->risk_level,
            'requires_action' => $this->requires_action,
            'resolved_at' => $this->resolved_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
        ];
    }
}
