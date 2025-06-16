<?php

// app/Http/Middleware/EnsureTwoFactorEnabled.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }

        if (!$user->two_factor_enabled) {
            return response()->json([
                'error' => 'Two-factor authentication is required for this action',
                'requires_two_factor_setup' => true,
            ], 403);
        }

        return $next($request);
    }
}