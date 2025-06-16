<?php
// app/Http/Controllers/Api/AdminController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OAuthProviderResource;
use App\Http\Resources\SecurityEventResource;
use App\Models\OAuthProvider;
use App\Models\SecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:system administration']);
    }

    public function systemStats(): JsonResponse
    {
        $stats = [
            'users' => [
                'total' => \App\Models\User::count(),
                'active' => \App\Models\User::where('is_active', true)->count(),
                'inactive' => \App\Models\User::where('is_active', false)->count(),
                'two_factor_enabled' => \App\Models\User::where('two_factor_enabled', true)->count(),
            ],
            'security' => [
                'total_events' => SecurityEvent::count(),
                'high_risk_events' => SecurityEvent::where('risk_level', 'high')->count(),
                'unresolved_events' => SecurityEvent::whereNull('resolved_at')
                    ->where('requires_action', true)->count(),
                'recent_failed_logins' => SecurityEvent::where('event_type', 'login_failed')
                    ->where('created_at', '>=', now()->subHours(24))->count(),
            ],
            'oauth' => [
                'providers_active' => OAuthProvider::where('is_active', true)->count(),
                'total_linked_accounts' => \App\Models\UserOAuthProvider::count(),
                'google_accounts' => \App\Models\UserOAuthProvider::whereHas('oauthProvider', function ($q) {
                    $q->where('name', 'google');
                })->count(),
                'discord_accounts' => \App\Models\UserOAuthProvider::whereHas('oauthProvider', function ($q) {
                    $q->where('name', 'discord');
                })->count(),
            ],
            'api' => [
                'total_keys' => \App\Models\ApiKey::count(),
                'active_keys' => \App\Models\ApiKey::where('is_active', true)->count(),
                'expired_keys' => \App\Models\ApiKey::where('expires_at', '<', now())->count(),
            ],
            'system' => [
                'laravel_version' => app()->version(),
                'php_version' => phpversion(),
                'database_type' => config('database.default'),
                'cache_driver' => config('cache.default'),
                'queue_driver' => config('queue.default'),
            ],
        ];

        return response()->json([
            'stats' => $stats,
            'generated_at' => now()->toISOString(),
        ]);
    }

    public function healthCheck(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
                'queue' => $this->checkQueue(),
            ],
            'timestamp' => now()->toISOString(),
        ];

        $overallStatus = collect($health['checks'])->every(fn($check) => $check['status'] === 'ok');
        $health['status'] = $overallStatus ? 'healthy' : 'unhealthy';

        return response()->json($health, $overallStatus ? 200 : 503);
    }

    public function logs(Request $request): JsonResponse
    {
        $request->validate([
            'lines' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'level' => ['sometimes', 'in:emergency,alert,critical,error,warning,notice,info,debug'],
        ]);

        $lines = $request->get('lines', 100);
        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile)) {
            return response()->json([
                'error' => 'Log file not found',
            ], 404);
        }

        $logs = collect(explode("\n", file_get_contents($logFile)))
            ->reverse()
            ->take($lines)
            ->values();

        return response()->json([
            'logs' => $logs,
            'total_lines' => $logs->count(),
        ]);
    }

    public function clearCache(): JsonResponse
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            return response()->json([
                'message' => 'Cache cleared successfully',
                'cleared' => ['cache', 'config', 'routes', 'views'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to clear cache',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function restartQueue(): JsonResponse
    {
        try {
            Artisan::call('queue:restart');

            return response()->json([
                'message' => 'Queue restart signal sent',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to restart queue',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function oauthProviders(): JsonResponse
    {
        $providers = OAuthProvider::all();

        return response()->json([
            'providers' => OAuthProviderResource::collection($providers),
        ]);
    }

    public function createOAuthProvider(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'unique:oauth_providers,name'],
            'display_name' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'config' => ['sometimes', 'array'],
        ]);

        $provider = OAuthProvider::create($request->validated());

        return response()->json([
            'message' => 'OAuth provider created successfully',
            'provider' => new OAuthProviderResource($provider),
        ], 201);
    }

    public function updateOAuthProvider(Request $request, OAuthProvider $provider): JsonResponse
    {
        $request->validate([
            'display_name' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'config' => ['sometimes', 'array'],
        ]);

        $provider->update($request->validated());

        return response()->json([
            'message' => 'OAuth provider updated successfully',
            'provider' => new OAuthProviderResource($provider),
        ]);
    }

    public function deleteOAuthProvider(OAuthProvider $provider): JsonResponse
    {
        if ($provider->userOAuthProviders()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete OAuth provider with linked accounts',
            ], 422);
        }

        $provider->delete();

        return response()->json([
            'message' => 'OAuth provider deleted successfully',
        ]);
    }

    public function securityEvents(Request $request): JsonResponse
    {
        $events = SecurityEvent::with('user')
            ->when($request->risk_level, function ($query, $riskLevel) {
                return $query->where('risk_level', $riskLevel);
            })
            ->when($request->requires_action, function ($query) {
                return $query->where('requires_action', true)->whereNull('resolved_at');
            })
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'events' => SecurityEventResource::collection($events),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function highRiskEvents(): JsonResponse
    {
        $events = SecurityEvent::with('user')
            ->where('risk_level', 'high')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'events' => SecurityEventResource::collection($events),
            'total' => $events->count(),
        ]);
    }

    public function resolveSecurityEvent(SecurityEvent $event): JsonResponse
    {
        $event->resolve();

        return response()->json([
            'message' => 'Security event resolved successfully',
            'event' => new SecurityEventResource($event),
        ]);
    }

    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Database connection failed'];
        }
    }

    protected function checkCache(): array
    {
        try {
            Cache::put('health_check', 'ok', 10);
            $value = Cache::get('health_check');
            return ['status' => $value === 'ok' ? 'ok' : 'error', 'message' => 'Cache working'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Cache not working'];
        }
    }

    protected function checkStorage(): array
    {
        try {
            $writable = is_writable(storage_path());
            return [
                'status' => $writable ? 'ok' : 'error',
                'message' => $writable ? 'Storage is writable' : 'Storage is not writable'
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Storage check failed'];
        }
    }

    protected function checkQueue(): array
    {
        try {
            // Simple queue check - try to get queue size
            $size = \Queue::size();
            return ['status' => 'ok', 'message' => "Queue accessible, size: {$size}"];
        } catch (\Exception $e) {
            return ['status' => 'warning', 'message' => 'Queue check failed'];
        }
    }
}