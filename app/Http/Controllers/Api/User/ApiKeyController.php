<?php
// app/Http/Controllers/Api/User/ApiKeyController.php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiKey\CreateApiKeyRequest;
use App\Http\Resources\ApiKeyResource;
use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function __construct(
        protected ApiKeyService $apiKeyService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $apiKeys = $user->getActiveApiKeys();

        return response()->json([
            'api_keys' => ApiKeyResource::collection($apiKeys),
            'total' => $apiKeys->count(),
            'limit' => 10,
        ]);
    }

    public function store(CreateApiKeyRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->apiKeyService->createApiKey(
                $user,
                $request->name,
                $request->scopes ?? [],
                $request->only(['allowed_ips', 'expires_at', 'rate_limit'])
            );

            return response()->json([
                'message' => 'API key created successfully',
                'api_key' => new ApiKeyResource($result['api_key']),
                'key' => $result['key'], // Only shown once
                'warning' => 'Save this key securely. It will not be shown again.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function show(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorize('view', $apiKey);

        return response()->json([
            'api_key' => new ApiKeyResource($apiKey),
        ]);
    }

    public function update(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorize('update', $apiKey);

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string', 'in:user:read,user:write,user:delete,admin:users,admin:roles,admin:system'],
            'allowed_ips' => ['sometimes', 'array'],
            'allowed_ips.*' => ['ip'],
            'rate_limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $apiKey->update($request->validated());

        $request->user()->logSecurityEvent('api_key_updated', [
            'api_key_id' => $apiKey->id,
            'api_key_name' => $apiKey->name,
        ]);

        return response()->json([
            'message' => 'API key updated successfully',
            'api_key' => new ApiKeyResource($apiKey->fresh()),
        ]);
    }

    public function destroy(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorize('delete', $apiKey);

        $user = $request->user();
        $success = $this->apiKeyService->revokeApiKey($user, $apiKey->id);

        if ($success) {
            return response()->json([
                'message' => 'API key revoked successfully',
            ]);
        }

        return response()->json([
            'error' => 'Failed to revoke API key',
        ], 400);
    }

    public function availableScopes(): JsonResponse
    {
        $apiKeyService = app(ApiKeyService::class);
        
        return response()->json([
            'scopes' => $apiKeyService->getAvailableScopes(),
        ]);
    }
}