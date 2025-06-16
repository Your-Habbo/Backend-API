<?php
// app/Http/Controllers/Api/Admin/RoleController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleCollection;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:view roles'])->only(['index', 'show']);
        $this->middleware(['permission:create roles'])->only(['store']);
        $this->middleware(['permission:edit roles'])->only(['update']);
        $this->middleware(['permission:delete roles'])->only(['destroy']);
        $this->middleware(['permission:assign roles'])->only(['givePermissions', 'revokePermissions', 'syncPermissions']);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'in:name,created_at,users_count,permissions_count'],
            'direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Role::with(['permissions']);

        // Search functionality
        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Sorting
        $sort = $request->sort ?? 'name';
        $direction = $request->direction ?? 'asc';

        if ($sort === 'users_count') {
            $query->withCount('users')->orderBy('users_count', $direction);
        } elseif ($sort === 'permissions_count') {
            $query->withCount('permissions')->orderBy('permissions_count', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        $roles = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'roles' => new RoleCollection($roles->items()),
            'pagination' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        // Assign permissions if provided
        if ($request->permissions) {
            $role->givePermissionTo($request->permissions);
        }

        // Log role creation
        auth()->user()->logSecurityEvent('role_created', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permissions' => $request->permissions ?? [],
        ]);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => new RoleResource($role->load('permissions')),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions', 'users']);

        return response()->json([
            'role' => new RoleResource($role),
            'statistics' => [
                'users_count' => $role->users()->count(),
                'permissions_count' => $role->permissions()->count(),
                'direct_users' => $role->users()->count(),
            ],
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        // Prevent updating system roles
        if (in_array($role->name, ['super-admin', 'admin', 'user', 'guest'])) {
            return response()->json([
                'error' => 'Cannot modify system roles',
            ], 422);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('roles')->ignore($role)],
        ]);

        $oldName = $role->name;
        $role->update($request->only('name'));

        // Log role update
        auth()->user()->logSecurityEvent('role_updated', [
            'role_id' => $role->id,
            'old_name' => $oldName,
            'new_name' => $role->name,
        ]);

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => new RoleResource($role->fresh(['permissions'])),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        // Prevent deleting system roles
        if (in_array($role->name, ['super-admin', 'admin', 'user', 'guest'])) {
            return response()->json([
                'error' => 'Cannot delete system roles',
            ], 422);
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete role that has assigned users. Remove users first.',
            ], 422);
        }

        // Log role deletion
        auth()->user()->logSecurityEvent('role_deleted', [
            'role_id' => $role->id,
            'role_name' => $role->name,
        ]);

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    public function permissions(Role $role): JsonResponse
    {
        return response()->json([
            'permissions' => $role->permissions->pluck('name'),
        ]);
    }

    public function givePermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->givePermissionTo($request->permissions);

        // Log permission assignment
        auth()->user()->logSecurityEvent('role_permissions_assigned', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permissions' => $request->permissions,
        ]);

        return response()->json([
            'message' => 'Permissions assigned successfully',
            'permissions' => $role->fresh()->permissions->pluck('name'),
        ]);
    }

    public function revokePermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->revokePermissionTo($request->permissions);

        // Log permission revocation
        auth()->user()->logSecurityEvent('role_permissions_revoked', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permissions' => $request->permissions,
        ]);

        return response()->json([
            'message' => 'Permissions revoked successfully',
            'permissions' => $role->fresh()->permissions->pluck('name'),
        ]);
    }

    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $oldPermissions = $role->permissions->pluck('name')->toArray();
        $role->syncPermissions($request->permissions);

        // Log permission sync
        auth()->user()->logSecurityEvent('role_permissions_synced', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'old_permissions' => $oldPermissions,
            'new_permissions' => $request->permissions,
        ]);

        return response()->json([
            'message' => 'Permissions synchronized successfully',
            'permissions' => $role->fresh()->permissions->pluck('name'),
        ]);
    }

    public function users(Request $request, Role $role): JsonResponse
    {
        $users = $role->users()
            ->when($request->search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'users' => UserResource::collection($users),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }
}

