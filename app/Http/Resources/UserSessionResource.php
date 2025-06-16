<?php

// app/Http/Resources/UserSessionResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'device_name' => $this->device_name,
            'device_info' => $this->device_info,
            'is_current' => $this->is_current,
            'last_activity' => $this->last_activity ? 
                \Carbon\Carbon::createFromTimestamp($this->last_activity)->toISOString() : null,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
