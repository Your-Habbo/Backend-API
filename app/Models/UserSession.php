<?php
// app/Models/UserSession.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
        'device_name',
        'device_info',
        'is_current',
    ];

    protected $casts = [
        'device_info' => 'array',
        'is_current' => 'boolean',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}