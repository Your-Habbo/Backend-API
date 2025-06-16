<?php
// app/Http/Controllers/Api/Admin/PermissionController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionCollection;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:view permissions'])->only(['index', 'show']);
        $this->middleware(['permission:create permissions'])->only(['store']);
        $this->middleware(['permission:edit permissions'])->only(['update']);
        $this->middleware(['permission:delete permissions'])->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string'],
            'sort' => ['sometimes', 'in:name,created_at,roles_count,users_count'],
            'direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Permission::with(['roles']);

        // Search functionality
        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Category filter (based on first word of permission name)
        if ($request->category) {
            $query->where('name', 'like', "{$request->category}%");
        }

        // Sorting
        $sort = $request->sort ?? 'name';
        $direction = $request->direction ?? 'asc';

        if ($sort === 'roles_count') {
            $query->withCount('roles')->orderBy('roles_count', $direction);
        } elseif ($sort === 'users_count') {
            $query->withCount('users')->orderBy('users_count', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        $permissions = $query->paginate($request->per_page ?? 15);

        // Get available categories
        $categories = Permission::select('name')
            ->get()
            ->map(function ($permission) {
                return explode(' ', $permission->name)[0];
            })
            ->unique()
            ->values();

        return response()->json([
            'permissions' => new PermissionCollection($permissions->items()),
            'categories' => $categories,
            'pagination' => [
                'current_page' => $permissions->currentPage(),
                'last_page' => $permissions->lastPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
        ]);

        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        // Log permission creation
        auth()->user()->logSecurityEvent('permission_created', [
            'permission_id' => $permission->id,
            'permission_name' => $permission->name,
        ]);

        return response()->json([
            'message' => 'Permission created successfully',
            'permission' => new PermissionResource($permission),
        ], 201);
    }

    public function show(Permission $permission): JsonResponse
    {
        $permission->load(['roles', 'users']);

        return response()->json([
            'permission' => new PermissionResource($permission),
            'statistics' => [
                'roles_count' => $permission->roles()->count(),
                'users_count' => $permission->users()->count(),
                'direct_users_count' => $permission->users()->wherePivot('model_type', 'App\Models\User')->count(),
            ],
        ]);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('permissions')->ignore($permission)],
        ]);

        $oldName = $permission->name;
        $permission->update($request->only('name'));

        // Log permission update
        auth()->user()->logSecurityEvent('permission_updated', [
            'permission_id' => $permission->id,
            'old_name' => $oldName,
            'new_name' => $permission->name,
        ]);

        return response()->json([
            'message' => 'Permission updated successfully',
            'permission' => new PermissionResource($permission->fresh(['roles'])),
        ]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        // Prevent deleting core system permissions
        $systemPermissions = [
            'view users', 'create users', 'edit users', 'delete users',
            'view roles', 'create roles', 'edit roles', 'delete roles',
            'view permissions', 'create permissions', 'edit permissions', 'delete permissions',
            'system administration'
        ];

        if (in_array($permission->name, $systemPermissions)) {
            return response()->json([
                'error' => 'Cannot delete system permissions',
            ], 422);
        }

        // Check if permission is assigned to roles or users
        if ($permission->roles()->count() > 0 || $permission->users()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete permission that is assigned to roles or users. Remove assignments first.',
            ], 422);
        }

        // Log permission deletion
        auth()->user()->logSecurityEvent('permission_deleted', [
            'permission_id' => $permission->id,
            'permission_name' => $permission->name,
        ]);

        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully',
        ]);
    }

    public function roles(Request $request, Permission $permission): JsonResponse
    {
        $roles = $permission->roles()
            ->when($request->search, function ($query, $search) {
                return $query->where('name', 'like', "%{$search}%");
            })
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'roles' => RoleResource::collection($roles),
            'pagination' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ],
        ]);
    }

    public function users(Request $request, Permission $permission): JsonResponse
    {
        $users = $permission->users()
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