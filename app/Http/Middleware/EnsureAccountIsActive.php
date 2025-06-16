<?php

// app/Http/Middleware/EnsureAccountIsActive.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'error' => 'Your account has been deactivated. Please contact support.',
            ], 403);
        }

        return $next($request);
    }
}