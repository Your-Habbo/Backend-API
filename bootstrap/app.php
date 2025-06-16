<?php
// bootstrap/app.php

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API middleware configuration for cross-domain requests
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'api.key' => \App\Http\Middleware\EnsureApiKeyIsValid::class,
            'scope' => \App\Http\Middleware\CheckApiScope::class,
            'active' => \App\Http\Middleware\EnsureAccountIsActive::class,
            '2fa' => \App\Http\Middleware\EnsureTwoFactorEnabled::class,
            'security.log' => \App\Http\Middleware\LogSecurityEvents::class,
            'maintenance' => \App\Http\Middleware\CheckMaintenanceMode::class,
        ]);

        // Trust all proxies for cloud deployment
        $middleware->trustProxies(at: '*');

        // Configure trusted hosts for YourHabbo domains
        $middleware->trustHosts(at: [
            'api-yourhabbo.codeneko.co',
            'frontend-yourhabbo.codeneko.co',
        ]);

        // Enable stateful API for SPA authentication
        $middleware->statefulApi();

        // Global middleware
        $middleware->append([
            \App\Http\Middleware\LogSecurityEvents::class,
        ]);

        // Web middleware group customization
        $middleware->web(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Custom middleware groups for YourHabbo
        $middleware->group('yourhabbo.api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnsureAccountIsActive::class,
        ]);

        $middleware->group('yourhabbo.admin', [
            'auth:sanctum',
            'active',
            'role:admin|super-admin',
            'security.log',
        ]);

        $middleware->group('maintenance.check', [
            \App\Http\Middleware\CheckMaintenanceMode::class,
        ]);

        // Rate limiting configuration
        $middleware->throttleApi();

        // Configure rate limiting for different endpoints
        $middleware->group('throttle.auth', [
            'throttle:login',  // 5 attempts per minute for login
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Custom exception handling for API responses
        $exceptions->render(function (Throwable $e, Request $request) {
            // Only handle API routes
            if ($request->is('api/*')) {
                // Authentication errors
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'error' => 'Unauthenticated',
                        'message' => 'Please login to access this resource',
                        'code' => 'AUTH_REQUIRED'
                    ], 401);
                }

                // Authorization errors
                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json([
                        'error' => 'Forbidden',
                        'message' => 'You do not have permission to access this resource',
                        'code' => 'INSUFFICIENT_PERMISSIONS'
                    ], 403);
                }

                // Validation errors
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'error' => 'Validation failed',
                        'message' => 'The given data was invalid',
                        'errors' => $e->errors(),
                        'code' => 'VALIDATION_ERROR'
                    ], 422);
                }

                // Model not found errors
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return response()->json([
                        'error' => 'Resource not found',
                        'message' => 'The requested resource was not found',
                        'code' => 'RESOURCE_NOT_FOUND'
                    ], 404);
                }

                // Route not found errors
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    return response()->json([
                        'error' => 'Endpoint not found',
                        'message' => 'The requested API endpoint was not found',
                        'code' => 'ENDPOINT_NOT_FOUND'
                    ], 404);
                }

                // Method not allowed errors
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                    return response()->json([
                        'error' => 'Method not allowed',
                        'message' => 'The HTTP method is not allowed for this endpoint',
                        'code' => 'METHOD_NOT_ALLOWED',
                        'allowed_methods' => $e->getHeaders()['Allow'] ?? null
                    ], 405);
                }

                // Rate limiting errors
                if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                    return response()->json([
                        'error' => 'Too many requests',
                        'message' => 'Rate limit exceeded. Please try again later',
                        'code' => 'RATE_LIMIT_EXCEEDED',
                        'retry_after' => $e->getHeaders()['Retry-After'] ?? null
                    ], 429);
                }

                // Database connection errors
                if ($e instanceof \Illuminate\Database\QueryException) {
                    \Log::error('Database error: ' . $e->getMessage());

                    if (!config('app.debug')) {
                        return response()->json([
                            'error' => 'Database error',
                            'message' => 'A database error occurred',
                            'code' => 'DATABASE_ERROR'
                        ], 500);
                    }
                }

                // Generic server errors (only in production)
                if (!config('app.debug')) {
                    \Log::error('Unhandled API exception: ' . $e->getMessage(), [
                        'exception' => $e,
                        'request' => $request->all(),
                        'url' => $request->fullUrl(),
                        'user_id' => $request->user()?->id,
                        'ip' => $request->ip(),
                    ]);

                    return response()->json([
                        'error' => 'Internal server error',
                        'message' => 'An unexpected error occurred',
                        'code' => 'INTERNAL_ERROR'
                    ], 500);
                }
            }

            // Let Laravel handle non-API exceptions normally
            return null;
        });
    })
    ->create();
