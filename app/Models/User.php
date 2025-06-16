<?php
// app/Models/User.php

namespace App\Models;

use App\Traits\HasApiKeys;
use App\Traits\HasTwoFactor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasApiKeys, HasTwoFactor;

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'email_verified_at',
        'two_factor_enabled',
        'security_settings',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_enabled' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
        'security_settings' => 'array',
        'last_login_at' => 'datetime',
        'login_history' => 'array',
        'is_active' => 'boolean',
    ];

    public function oauthProviders(): HasMany
    {
        return $this->hasMany(UserOAuthProvider::class);
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function getOAuthProvider(string $provider): ?UserOAuthProvider
    {
        return $this->oauthProviders()
            ->whereHas('oauthProvider', function ($query) use ($provider) {
                $query->where('name', $provider);
            })
            ->first();
    }

    public function hasOAuthProvider(string $provider): bool
    {
        return $this->getOAuthProvider($provider) !== null;
    }

    public function linkOAuthProvider(string $provider, array $providerData): UserOAuthProvider
    {
        $oauthProvider = OAuthProvider::where('name', $provider)->firstOrFail();
        
        return $this->oauthProviders()->updateOrCreate(
            ['oauth_provider_id' => $oauthProvider->id],
            [
                'provider_user_id' => $providerData['id'],
                'provider_username' => $providerData['username'] ?? null,
                'provider_email' => $providerData['email'] ?? null,
                'provider_avatar' => $providerData['avatar'] ?? null,
                'provider_data' => $providerData,
                'last_used_at' => now(),
            ]
        );
    }

    public function unlinkOAuthProvider(string $provider): bool
    {
        return $this->oauthProviders()
            ->whereHas('oauthProvider', function ($query) use ($provider) {
                $query->where('name', $provider);
            })
            ->delete();
    }

    public function updateLoginInfo(string $ipAddress, string $userAgent): void
    {
        $loginHistory = $this->login_history ?? [];
        
        // Keep only last 10 logins
        if (count($loginHistory) >= 10) {
            array_shift($loginHistory);
        }
        
        $loginHistory[] = [
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
            'timestamp' => now()->toISOString(),
        ];

        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
            'login_history' => $loginHistory,
        ]);
    }

    public function logSecurityEvent(string $eventType, array $data = []): SecurityEvent
    {
        return $this->securityEvents()->create([
            'event_type' => $eventType,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'event_data' => $data,
            'risk_level' => $this->calculateRiskLevel($eventType, $data),
        ]);
    }

    protected function calculateRiskLevel(string $eventType, array $data): string
    {
        $highRiskEvents = ['failed_login_multiple', 'password_change', 'two_factor_disabled'];
        $mediumRiskEvents = ['login_new_device', 'login_new_location', 'api_key_created'];
        
        if (in_array($eventType, $highRiskEvents)) {
            return 'high';
        }
        
        if (in_array($eventType, $mediumRiskEvents)) {
            return 'medium';
        }
        
        return 'low';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithTwoFactor($query)
    {
        return $query->where('two_factor_enabled', true);
    }

    public function getPermissionNames(): array
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    public function getRoleNames(): array
    {
        return $this->roles->pluck('name')->toArray();
    }
}