<?php


// app/Http/Middleware/CheckMaintenanceMode.php

namespace App\Http\Middleware;

use App\Models\MaintenanceSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip maintenance check for certain routes
        if ($this->shouldSkipMaintenanceCheck($request)) {
            return $next($request);
        }

        $maintenance = MaintenanceSetting::current();
        
        if (!$maintenance->isMaintenanceActive()) {
            return $next($request);
        }

        $user = $request->user();
        $ip = $request->ip();

        // Check if user can bypass maintenance mode
        if ($maintenance->canUserBypass($user, $ip)) {
            return $next($request);
        }

        // Return maintenance mode response
        return response()->json([
            'error' => 'Maintenance mode',
            'message' => 'The application is currently under maintenance',
            'maintenance' => $maintenance->getMaintenanceStatus(),
        ], 503);
    }

    protected function shouldSkipMaintenanceCheck(Request $request): bool
    {
        $skipRoutes = [
            'api/maintenance/status',
            'api/maintenance/check',
            'api/admin/maintenance/*',
            'api/auth/login', // Allow login during maintenance
            'api/health', // Health check
        ];

        foreach ($skipRoutes as $route) {
            if ($request->is($route)) {
                return true;
            }
        }

        return false;
    }
}
