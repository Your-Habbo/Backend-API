<?php
// app/Http/Middleware/EnsureApiKeyIsValid.php

namespace App\Http\Middleware;

use App\Services\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyIsValid
{
    public function __construct(
        protected ApiKeyService $apiKeyService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->extractApiKey($request);
        
        if (!$apiKey) {
            return response()->json([
                'error' => 'API key is required',
            ], 401);
        }

        $user = $this->apiKeyService->validateApiKey($apiKey, $request);
        
        if (!$user) {
            return response()->json([
                'error' => 'Invalid API key',
            ], 401);
        }

        // Set the authenticated user
        auth()->setUser($user);
        
        // Add API key info to request
        $request->merge(['api_key_authenticated' => true]);

        return $next($request);
    }

    protected function extractApiKey(Request $request): ?string
    {
        // Check Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            if (str_starts_with($token, 'ak_')) {
                return $token;
            }
        }

        // Check X-API-Key header
        $apiKeyHeader = $request->header('X-API-Key');
        if ($apiKeyHeader && str_starts_with($apiKeyHeader, 'ak_')) {
            return $apiKeyHeader;
        }

        // Check query parameter
        $apiKeyQuery = $request->query('api_key');
        if ($apiKeyQuery && str_starts_with($apiKeyQuery, 'ak_')) {
            return $apiKeyQuery;
        }

        return null;
    }
}
