<?php
// app/Http/Middleware/CheckPermission.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }

        // Check if user has any of the required permissions
        if (!$user->hasAnyPermission($permissions)) {
            return response()->json([
                'error' => 'Insufficient permissions. Required permissions: ' . implode(', ', $permissions),
            ], 403);
        }

        return $next($request);
    }
}