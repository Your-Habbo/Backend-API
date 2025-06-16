<?php   

// app/Models/MaintenanceSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MaintenanceSetting extends Model
{
    protected $fillable = [
        'is_enabled',
        'title',
        'message',
        'scheduled_start',
        'scheduled_end',
        'allowed_ips',
        'allowed_roles',
        'allow_admin_access',
        'contact_email',
        'social_links',
        'estimated_duration',
        'internal_notes',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'allowed_ips' => 'array',
        'allowed_roles' => 'array',
        'allow_admin_access' => 'boolean',
        'social_links' => 'array',
    ];

    public static function current(): self
    {
        return Cache::remember('maintenance_settings', 300, function () {
            return self::first() ?? self::create([]);
        });
    }

    public function isMaintenanceActive(): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        // Check if scheduled maintenance is within time window
        if ($this->scheduled_start && $this->scheduled_end) {
            $now = now();
            return $now->between($this->scheduled_start, $this->scheduled_end);
        }

        // If no schedule, maintenance is active if enabled
        return true;
    }

    public function canUserBypass($user = null, $ip = null): bool
    {
        // Allow admin access if enabled
        if ($this->allow_admin_access && $user && $user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Check allowed roles
        if ($this->allowed_roles && $user) {
            foreach ($this->allowed_roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        // Check allowed IPs
        if ($this->allowed_ips && $ip) {
            return in_array($ip, $this->allowed_ips);
        }

        return false;
    }

    public function getMaintenanceStatus(): array
    {
        return [
            'is_maintenance_mode' => $this->isMaintenanceActive(),
            'title' => $this->title,
            'message' => $this->message,
            'scheduled_start' => $this->scheduled_start?->toISOString(),
            'scheduled_end' => $this->scheduled_end?->toISOString(),
            'estimated_duration' => $this->estimated_duration,
            'contact_email' => $this->contact_email,
            'social_links' => $this->social_links,
        ];
    }

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('maintenance_settings');
        });
    }
}

// app/Http/Controllers/Api/MaintenanceController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function status(): JsonResponse
    {
        $maintenance = MaintenanceSetting::current();
        
        return response()->json([
            'maintenance' => $maintenance->getMaintenanceStatus(),
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $maintenance = MaintenanceSetting::current();
        $user = $request->user();
        $ip = $request->ip();

        $isMaintenanceActive = $maintenance->isMaintenanceActive();
        $canBypass = $maintenance->canUserBypass($user, $ip);

        return response()->json([
            'maintenance' => [
                'is_active' => $isMaintenanceActive,
                'can_bypass' => $canBypass,
                'should_show_maintenance' => $isMaintenanceActive && !$canBypass,
                'title' => $maintenance->title,
                'message' => $maintenance->message,
                'estimated_duration' => $maintenance->estimated_duration,
                'contact_email' => $maintenance->contact_email,
                'social_links' => $maintenance->social_links,
                'scheduled_end' => $maintenance->scheduled_end?->toISOString(),
            ],
        ]);
    }
}