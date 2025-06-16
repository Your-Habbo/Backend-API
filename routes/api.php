<?php
// routes/api.php - Fixed version with all imports

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\OAuthController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\User\ApiKeyController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\AdminController;  // This was missing!
use App\Http\Controllers\Api\WebhookController; // This was missing!
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {
    // Basic authentication
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // OAuth routes
    Route::prefix('oauth')->group(function () {
        Route::get('/{provider}/redirect', [OAuthController::class, 'redirect']);
        Route::get('/{provider}/callback', [OAuthController::class, 'callback']);
    });
    
    // Password reset routes (these methods need to be added to AuthController)
    Route::post('/password/email', [AuthController::class, 'sendResetLinkEmail']);
    Route::post('/password/reset', [AuthController::class, 'reset']);
});

// CSRF token for SPA
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
});

// Protected routes (authentication required)
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    
    // Basic auth routes
    Route::prefix('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        
        // Two-factor authentication
        Route::prefix('two-factor')->group(function () {
            Route::post('/enable', [TwoFactorController::class, 'enable']);
            Route::post('/confirm', [TwoFactorController::class, 'confirm']);
            Route::post('/verify', [TwoFactorController::class, 'verify']);
            Route::delete('/disable', [TwoFactorController::class, 'disable']);
            Route::get('/recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
            Route::post('/recovery-codes', [TwoFactorController::class, 'generateRecoveryCodes']);
        });
        
        // OAuth account linking
        Route::prefix('oauth')->group(function () {
            Route::get('/linked', [OAuthController::class, 'linked']);
            Route::post('/{provider}/link', [OAuthController::class, 'link']);
            Route::delete('/{provider}/unlink', [OAuthController::class, 'unlink']);
        });
    });
    
    // User profile management
    Route::prefix('user')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);
        
        // User settings
        Route::get('/settings', [ProfileController::class, 'settings']);
        Route::put('/settings', [ProfileController::class, 'updateSettings']);
        
        // Security information
        Route::get('/security', [ProfileController::class, 'security']);
        Route::get('/sessions', [ProfileController::class, 'sessions']);
        Route::delete('/sessions/{session}', [ProfileController::class, 'revokeSession']);
        Route::get('/security-events', [ProfileController::class, 'securityEvents']);
        
        // API key management
        Route::prefix('api-keys')->group(function () {
            Route::get('/', [ApiKeyController::class, 'index']);
            Route::post('/', [ApiKeyController::class, 'store']);
            Route::get('/{apiKey}', [ApiKeyController::class, 'show']);
            Route::put('/{apiKey}', [ApiKeyController::class, 'update']);
            Route::delete('/{apiKey}', [ApiKeyController::class, 'destroy']);
            Route::get('/scopes/available', [ApiKeyController::class, 'availableScopes']);
        });
        
        // User permissions and roles
        Route::get('/permissions', [ProfileController::class, 'permissions']);
        Route::get('/roles', [ProfileController::class, 'roles']);
    });
    
    // Admin routes (role-based access)
    Route::prefix('admin')->middleware(['role:admin|super-admin'])->group(function () {
        
        // User management
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::post('/', [AdminUserController::class, 'store']);
            Route::get('/{user}', [AdminUserController::class, 'show']);
            Route::put('/{user}', [AdminUserController::class, 'update']);
            Route::delete('/{user}', [AdminUserController::class, 'destroy']);
            
            // User-specific actions
            Route::post('/{user}/activate', [AdminUserController::class, 'activate']);
            Route::post('/{user}/deactivate', [AdminUserController::class, 'deactivate']);
            Route::post('/{user}/force-logout', [AdminUserController::class, 'forceLogout']);
            Route::post('/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
            Route::post('/{user}/verify-email', [AdminUserController::class, 'verifyEmail']);
            Route::delete('/{user}/two-factor', [AdminUserController::class, 'disableTwoFactor']);
            
            // User roles and permissions
            Route::get('/{user}/roles', [AdminUserController::class, 'roles']);
            Route::post('/{user}/roles', [AdminUserController::class, 'assignRoles']);
            Route::delete('/{user}/roles', [AdminUserController::class, 'removeRoles']);
            Route::get('/{user}/permissions', [AdminUserController::class, 'permissions']);
            Route::post('/{user}/permissions', [AdminUserController::class, 'givePermissions']);
            Route::delete('/{user}/permissions', [AdminUserController::class, 'revokePermissions']);
            
            // User security
            Route::get('/{user}/security-events', [AdminUserController::class, 'securityEvents']);
            Route::get('/{user}/sessions', [AdminUserController::class, 'sessions']);
            Route::delete('/{user}/sessions/{session}', [AdminUserController::class, 'revokeSession']);
            Route::get('/{user}/api-keys', [AdminUserController::class, 'apiKeys']);
            Route::delete('/{user}/api-keys/{apiKey}', [AdminUserController::class, 'revokeApiKey']);
        });
        
        // Role management
        Route::prefix('roles')->middleware(['permission:manage roles'])->group(function () {
            Route::get('/', [RoleController::class, 'index']);
            Route::post('/', [RoleController::class, 'store']);
            Route::get('/{role}', [RoleController::class, 'show']);
            Route::put('/{role}', [RoleController::class, 'update']);
            Route::delete('/{role}', [RoleController::class, 'destroy']);
            
            // Role permissions
            Route::get('/{role}/permissions', [RoleController::class, 'permissions']);
            Route::post('/{role}/permissions', [RoleController::class, 'givePermissions']);
            Route::delete('/{role}/permissions', [RoleController::class, 'revokePermissions']);
            Route::post('/{role}/permissions/sync', [RoleController::class, 'syncPermissions']);
            
            // Role users
            Route::get('/{role}/users', [RoleController::class, 'users']);
        });
        
        // Permission management
        Route::prefix('permissions')->middleware(['permission:manage permissions'])->group(function () {
            Route::get('/', [PermissionController::class, 'index']);
            Route::post('/', [PermissionController::class, 'store']);
            Route::get('/{permission}', [PermissionController::class, 'show']);
            Route::put('/{permission}', [PermissionController::class, 'update']);
            Route::delete('/{permission}', [PermissionController::class, 'destroy']);
            
            // Permission roles and users
            Route::get('/{permission}/roles', [PermissionController::class, 'roles']);
            Route::get('/{permission}/users', [PermissionController::class, 'users']);
        });
        
        // System administration
        Route::prefix('system')->middleware(['permission:system administration'])->group(function () {
            Route::get('/stats', [AdminController::class, 'systemStats']);
            Route::get('/health', [AdminController::class, 'healthCheck']);
            Route::get('/logs', [AdminController::class, 'logs']);
            Route::post('/cache/clear', [AdminController::class, 'clearCache']);
            Route::post('/queue/restart', [AdminController::class, 'restartQueue']);
            
            // OAuth provider management
            Route::get('/oauth-providers', [AdminController::class, 'oauthProviders']);
            Route::post('/oauth-providers', [AdminController::class, 'createOAuthProvider']);
            Route::put('/oauth-providers/{provider}', [AdminController::class, 'updateOAuthProvider']);
            Route::delete('/oauth-providers/{provider}', [AdminController::class, 'deleteOAuthProvider']);
            
            // Security events
            Route::get('/security-events', [AdminController::class, 'securityEvents']);
            Route::get('/security-events/high-risk', [AdminController::class, 'highRiskEvents']);
            Route::post('/security-events/{event}/resolve', [AdminController::class, 'resolveSecurityEvent']);

            
        });
    });
});

// Health check endpoint (public)
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
    ]);
});

// API documentation endpoint
Route::get('/docs', function () {
    return response()->json([
        'message' => 'API Documentation',
        'endpoints' => [
            'auth' => '/api/auth/*',
            'user' => '/api/user/*',
            'admin' => '/api/admin/*',
        ],
        'authentication' => [
            'sanctum' => 'Bearer token authentication',
        ],
    ]);
});

// Public maintenance status routes (no authentication required)
Route::prefix('maintenance')->group(function () {
    Route::get('/status', [App\Http\Controllers\Api\MaintenanceController::class, 'status']);
    Route::get('/check', [App\Http\Controllers\Api\MaintenanceController::class, 'check']);
});


// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'error' => 'Endpoint not found',
        'message' => 'The requested API endpoint does not exist',
    ], 404);
});