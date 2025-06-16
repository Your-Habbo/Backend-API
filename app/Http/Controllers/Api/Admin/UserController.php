<?php
// app/Http/Controllers/Api/Admin/UserController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\ApiKeyResource;
use App\Http\Resources\SecurityEventResource;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserSessionResource;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:view users'])->only(['index', 'show']);
        $this->middleware(['permission:create users'])->only(['store']);
        $this->middleware(['permission:edit users'])->only(['update', 'activate', 'deactivate', 'resetPassword', 'verifyEmail']);
        $this->middleware(['permission:delete users'])->only(['destroy']);
        $this->middleware(['permission:manage user two factor'])->only(['disableTwoFactor']);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string'],
            'status' => ['sometimes', 'in:active,inactive,all'],
            'two_factor' => ['sometimes', 'in:enabled,disabled,all'],
            'sort' => ['sometimes', 'in:name,email,created_at,last_login_at'],
            'direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::with(['roles', 'oauthProviders.oauthProvider']);

        // Search functionality
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($request->role) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Status filter
        if ($request->status && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        // Two-factor filter
        if ($request->two_factor && $request->two_factor !== 'all') {
            $query->where('two_factor_enabled', $request->two_factor === 'enabled');
        }

        // Sorting
        $sort = $request->sort ?? 'created_at';
        $direction = $request->direction ?? 'desc';
        $query->orderBy($sort, $direction);

        $users = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'users' => new UserCollection($users->items()),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['email_verified_at'] = $data['email_verified'] ?? false ? now() : null;
        
        $user = User::create($data);

        // Assign roles
        if (!empty($data['roles'])) {
            $user->assignRole($data['roles']);
        } else {
            $user->assignRole('user');
        }

        // Log creation
        auth()->user()->logSecurityEvent('user_created_by_admin', [
            'created_user_id' => $user->id,
            'created_user_email' => $user->email,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => new UserResource($user->load(['roles', 'oauthProviders.oauthProvider'])),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles.permissions', 'oauthProviders.oauthProvider']);

        return response()->json([
            'user' => new UserResource($user),
            'statistics' => [
                'total_logins' => count($user->login_history ?? []),
                'api_keys_count' => $user->apiKeys()->count(),
                'active_sessions_count' => $user->sessions()->count(),
                'security_events_count' => $user->securityEvents()->count(),
                'high_risk_events_count' => $user->securityEvents()->where('risk_level', 'high')->count(),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        // Handle password update
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Handle email verification
        if (isset($data['email_verified'])) {
            $data['email_verified_at'] = $data['email_verified'] ? now() : null;
            unset($data['email_verified']);
        }

        // Handle two-factor
        if (isset($data['two_factor_enabled']) && !$data['two_factor_enabled']) {
            $data['two_factor_secret'] = null;
            $data['two_factor_recovery_codes'] = null;
            $data['two_factor_confirmed_at'] = null;
        }

        $user->update($data);

        // Update roles
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // Log update
        auth()->user()->logSecurityEvent('user_updated_by_admin', [
            'updated_user_id' => $user->id,
            'updated_user_email' => $user->email,
            'changes' => array_keys($data),
        ]);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => new UserResource($user->fresh(['roles', 'oauthProviders.oauthProvider'])),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        // Prevent deletion of current user
        if ($user->id === auth()->id()) {
            return response()->json([
                'error' => 'Cannot delete your own account',
            ], 422);
        }

        // Prevent deletion of super admin
        if ($user->hasRole('super-admin')) {
            return response()->json([
                'error' => 'Cannot delete super admin account',
            ], 422);
        }

        // Log deletion
        auth()->user()->logSecurityEvent('user_deleted_by_admin', [
            'deleted_user_id' => $user->id,
            'deleted_user_email' => $user->email,
        ]);

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function activate(User $user): JsonResponse
    {
        $user->update(['is_active' => true]);

        auth()->user()->logSecurityEvent('user_activated_by_admin', [
            'activated_user_id' => $user->id,
            'activated_user_email' => $user->email,
        ]);

        return response()->json([
            'message' => 'User activated successfully',
            'user' => new UserResource($user),
        ]);
    }

    public function deactivate(User $user): JsonResponse
    {
        // Prevent deactivation of current user
        if ($user->id === auth()->id()) {
            return response()->json([
                'error' => 'Cannot deactivate your own account',
            ], 422);
        }

        // Prevent deactivation of super admin
        if ($user->hasRole('super-admin')) {
            return response()->json([
                'error' => 'Cannot deactivate super admin account',
            ], 422);
        }

        $user->update(['is_active' => false]);

        // Revoke all user tokens
        $user->tokens()->delete();

        auth()->user()->logSecurityEvent('user_deactivated_by_admin', [
            'deactivated_user_id' => $user->id,
            'deactivated_user_email' => $user->email,
        ]);

        return response()->json([
            'message' => 'User deactivated successfully',
            'user' => new UserResource($user),
        ]);
    }

    public function forceLogout(User $user): JsonResponse
    {
        // Revoke all user tokens
        $user->tokens()->delete();

        // Delete all sessions
        $user->sessions()->delete();

        auth()->user()->logSecurityEvent('user_force_logout_by_admin', [
            'logged_out_user_id' => $user->id,
            'logged_out_user_email' => $user->email,
        ]);

        return response()->json([
            'message' => 'User logged out from all devices successfully',
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'send_email' => ['sometimes', 'boolean'],
        ]);

        $newPassword = Str::random(12);
        $user->update(['password' => Hash::make($newPassword)]);

        // Revoke all tokens to force re-login
        $user->tokens()->delete();

        auth()->user()->logSecurityEvent('password_reset_by_admin', [
            'reset_user_id' => $user->id,
            'reset_user_email' => $user->email,
        ]);

        return response()->json([
            'message' => 'Password reset successfully',
            'temporary_password' => $newPassword,
            'warning' => 'Share this password securely with the user',
        ]);
    }

    public function verifyEmail(User $user): JsonResponse
    {
        $user->update(['email_verified_at' => now()]);

        auth()->user()->logSecurityEvent('email_verified_by_admin', [
            'verified_user_id' => $user->id,
            'verified_user_email' => $user->email,
        ]);

        return response()->json([
            'message' => 'Email verified successfully',
            'user' => new UserResource($user),
        ]);
    }

    public function disableTwoFactor(User $user): JsonResponse
    {
        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        auth()->user()->logSecurityEvent('two_factor_disabled_by_admin', [
            'disabled_user_id' => $user->id,
            'disabled_user_email' => $user->email,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully',
            'user' => new UserResource($user),
        ]);
    }

    // Role and permission management methods
    public function roles(User $user): JsonResponse
    {
        return response()->json([
            'roles' => $user->roles->pluck('name'),
        ]);
    }

    public function assignRoles(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user->assignRole($request->roles);

        return response()->json([
            'message' => 'Roles assigned successfully',
            'roles' => $user->fresh()->roles->pluck('name'),
        ]);
    }

    public function removeRoles(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user->removeRole($request->roles);

        return response()->json([
            'message' => 'Roles removed successfully',
            'roles' => $user->fresh()->roles->pluck('name'),
        ]);
    }

    public function permissions(User $user): JsonResponse
    {
        return response()->json([
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
            'role_permissions' => $user->getPermissionsViaRoles()->pluck('name'),
        ]);
    }

    public function givePermissions(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user->givePermissionTo($request->permissions);

        return response()->json([
            'message' => 'Permissions granted successfully',
            'permissions' => $user->fresh()->getAllPermissions()->pluck('name'),
        ]);
    }

    public function revokePermissions(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user->revokePermissionTo($request->permissions);

        return response()->json([
            'message' => 'Permissions revoked successfully',
            'permissions' => $user->fresh()->getAllPermissions()->pluck('name'),
        ]);
    }

    // Security and monitoring methods
    public function securityEvents(Request $request, User $user): JsonResponse
    {
        $events = $user->securityEvents()
            ->when($request->risk_level, function ($query, $riskLevel) {
                return $query->where('risk_level', $riskLevel);
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

    public function sessions(User $user): JsonResponse
    {
        $sessions = UserSession::where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->get();

        return response()->json([
            'sessions' => UserSessionResource::collection($sessions),
        ]);
    }

    public function revokeSession(User $user, string $sessionId): JsonResponse
    {
        $session = UserSession::where('user_id', $user->id)
            ->where('id', $sessionId)
            ->first();

        if (!$session) {
            return response()->json([
                'error' => 'Session not found',
            ], 404);
        }

        $session->delete();

        auth()->user()->logSecurityEvent('session_revoked_by_admin', [
            'revoked_user_id' => $user->id,
            'session_id' => $sessionId,
        ]);

        return response()->json([
            'message' => 'Session revoked successfully',
        ]);
    }

    public function apiKeys(User $user): JsonResponse
    {
        $apiKeys = $user->apiKeys;

        return response()->json([
            'api_keys' => ApiKeyResource::collection($apiKeys),
        ]);
    }

    public function revokeApiKey(User $user, int $apiKeyId): JsonResponse
    {
        $apiKey = $user->apiKeys()->find($apiKeyId);

        if (!$apiKey) {
            return response()->json([
                'error' => 'API key not found',
            ], 404);
        }

        $apiKey->delete();

        auth()->user()->logSecurityEvent('api_key_revoked_by_admin', [
            'revoked_user_id' => $user->id,
            'api_key_id' => $apiKeyId,
        ]);

        return response()->json([
            'message' => 'API key revoked successfully',
        ]);
    }
}