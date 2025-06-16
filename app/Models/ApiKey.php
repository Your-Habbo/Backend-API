<?php

// app/Models/ApiKey.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'key_hash',
        'key_prefix',
        'scopes',
        'allowed_ips',
        'expires_at',
        'is_active',
        'rate_limit',
    ];

    protected $casts = [
        'scopes' => 'array',
        'allowed_ips' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'key_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateKey(): array
    {
        $key = 'ak_' . Str::random(32);
        $prefix = substr($key, 0, 10);
        $hash = Hash::make($key);

        return [
            'key' => $key,
            'prefix' => $prefix,
            'hash' => $hash,
        ];
    }

    public function validateKey(string $key): bool
    {
        return Hash::check($key, $this->key_hash);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true;
        }

        return in_array($ip, $this->allowed_ips);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }
}
