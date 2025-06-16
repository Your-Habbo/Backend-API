<?php
// app/Http/Controllers/Api/User/ProfileController.php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\SecurityEventResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserSessionResource;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load(['roles', 'oauthProviders.oauthProvider'])),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Handle password update
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
            unset($data['current_password']);
            
            // Log password change
            $user->logSecurityEvent('password_changed');
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:DELETE MY ACCOUNT'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Invalid password',
            ], 422);
        }

        // Log account deletion
        $user->logSecurityEvent('account_deleted');

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete user account
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'settings' => $user->security_settings ?? [],
            'defaults' => [
                'email_notifications' => true,
                'security_alerts' => true,
                'two_factor_required' => false,
                'session_timeout' => 30, // minutes
                'login_alerts' => true,
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'email_notifications' => ['sometimes', 'boolean'],
            'security_alerts' => ['sometimes', 'boolean'],
            'two_factor_required' => ['sometimes', 'boolean'],
            'session_timeout' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'login_alerts' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $settings = $user->security_settings ?? [];
        
        foreach ($request->validated() as $key => $value) {
            $settings[$key] = $value;
        }

        $user->update(['security_settings' => $settings]);

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => $settings,
        ]);
    }

    public function security(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'two_factor_enabled' => $user->two_factor_enabled,
            'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toISOString(),
            'recovery_codes_count' => $user->getRecoveryCodes()->count(),
            'active_sessions_count' => $user->sessions()->count(),
            'api_keys_count' => $user->apiKeys()->active()->count(),
            'oauth_providers' => $user->oauthProviders()->with('oauthProvider')->get()
                ->map(function ($provider) {
                    return [
                        'provider' => $provider->oauthProvider->name,
                        'display_name' => $provider->oauthProvider->display_name,
                        'linked_at' => $provider->created_at->toISOString(),
                        'last_used_at' => $provider->last_used_at?->toISOString(),
                    ];
                }),
            'recent_security_events' => SecurityEventResource::collection(
                $user->securityEvents()
                    ->latest()
                    ->limit(10)
                    ->get()
            ),
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentSessionId = session()->getId();
        
        $sessions = UserSession::where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->get()
            ->map(function ($session) use ($currentSessionId) {
                $session->is_current = $session->id === $currentSessionId;
                return $session;
            });

        return response()->json([
            'sessions' => UserSessionResource::collection($sessions),
        ]);
    }

    public function revokeSession(Request $request, string $sessionId): JsonResponse
    {
        $user = $request->user();
        $currentSessionId = session()->getId();
        
        if ($sessionId === $currentSessionId) {
            return response()->json([
                'error' => 'Cannot revoke current session',
            ], 422);
        }

        $session = UserSession::where('user_id', $user->id)
            ->where('id', $sessionId)
            ->first();

        if (!$session) {
            return response()->json([
                'error' => 'Session not found',
            ], 404);
        }

        $session->delete();

        $user->logSecurityEvent('session_revoked', [
            'session_id' => $sessionId,
            'revoked_session_ip' => $session->ip_address,
        ]);

        return response()->json([
            'message' => 'Session revoked successfully',
        ]);
    }

    public function securityEvents(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $events = $user->securityEvents()
            ->when($request->risk_level, function ($query, $riskLevel) {
                return $query->where('risk_level', $riskLevel);
            })
            ->when($request->event_type, function ($query, $eventType) {
                return $query->where('event_type', $eventType);
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

    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->roles->pluck('name'),
        ]);
    }

    public function roles(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'roles' => $user->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions_count' => $role->permissions()->count(),
                ];
            }),
        ]);
    }
}

