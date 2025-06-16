<?php

// app/Models/SecurityEvent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'event_data',
        'risk_level',
        'requires_action',
        'resolved_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'requires_action' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeHighRisk($query)
    {
        return $query->where('risk_level', 'high');
    }

    public function scopeRequiresAction($query)
    {
        return $query->where('requires_action', true)->whereNull('resolved_at');
    }

    public function resolve(): void
    {
        $this->update(['resolved_at' => now()]);
    }
}
