<?php

// app/Http/Middleware/CheckApiScope.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiScope
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }

        // If authenticated via API key, check scopes
        if ($request->has('api_key_authenticated')) {
            $apiKey = $user->apiKeys()
                ->where('key_prefix', substr($this->extractApiKey($request), 0, 10))
                ->active()
                ->first();

            if (!$apiKey) {
                return response()->json([
                    'error' => 'Invalid API key',
                ], 401);
            }

            // Check if API key has any of the required scopes
            $hasScope = false;
            foreach ($scopes as $scope) {
                if ($apiKey->hasScope($scope)) {
                    $hasScope = true;
                    break;
                }
            }

            if (!$hasScope) {
                return response()->json([
                    'error' => 'Insufficient API key scopes. Required scopes: ' . implode(', ', $scopes),
                ], 403);
            }
        }

        return $next($request);
    }

    protected function extractApiKey(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return $request->header('X-API-Key') ?? $request->query('api_key');
    }
}