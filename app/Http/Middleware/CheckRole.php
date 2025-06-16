<?php
// app/Http/Middleware/CheckRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }

        // Check if user has any of the required roles
        if (!$user->hasAnyRole($roles)) {
            return response()->json([
                'error' => 'Insufficient permissions. Required roles: ' . implode(', ', $roles),
            ], 403);
        }

        return $next($request);
    }
}
