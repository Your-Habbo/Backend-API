<?php

// app/Http/Controllers/Api/Auth/OAuthController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OAuthController extends Controller
{
    public function __construct(
        protected OAuthService $oauthService
    ) {}

    public function redirect(string $provider): JsonResponse
    {
        try {
            $redirectUrl = $this->oauthService->getRedirectUrl($provider);
            
            return response()->json([
                'redirect_url' => $redirectUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function callback(string $provider): JsonResponse
    {
        try {
            $result = $this->oauthService->handleCallback($provider);
            $user = $result['user'];
            
            // Update login information
            $user->updateLoginInfo(request()->ip(), request()->userAgent());
            
            // Create auth token
            $token = $user->createToken('auth-token')->plainTextToken;
            
            // Log security event
            $eventType = $result['is_new_user'] ? 'oauth_registration' : 'oauth_login';
            $user->logSecurityEvent($eventType, [
                'provider' => $provider,
                'is_new_user' => $result['is_new_user'],
                'is_new_link' => $result['is_new_link'],
            ]);

            return response()->json([
                'message' => $result['is_new_user'] ? 'Registration successful' : 'Login successful',
                'user' => new UserResource($user),
                'token' => $token,
                'provider' => $provider,
                'is_new_user' => $result['is_new_user'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function link(Request $request, string $provider): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->oauthService->linkAccount($user, $provider);
            
            return response()->json([
                'message' => ucfirst($provider) . ' account linked successfully',
                'linked_account' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function unlink(Request $request, string $provider): JsonResponse
    {
        try {
            $user = $request->user();
            $success = $this->oauthService->unlinkAccount($user, $provider);
            
            if ($success) {
                return response()->json([
                    'message' => ucfirst($provider) . ' account unlinked successfully',
                ]);
            }
            
            return response()->json([
                'error' => 'Failed to unlink account',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function linked(Request $request): JsonResponse
    {
        $user = $request->user();
        $linkedAccounts = $user->oauthProviders()->with('oauthProvider')->get()
            ->map(function ($link) {
                return [
                    'provider' => $link->oauthProvider->name,
                    'provider_username' => $link->provider_username,
                    'provider_email' => $link->provider_email,
                    'linked_at' => $link->created_at,
                    'last_used_at' => $link->last_used_at,
                ];
            });

        return response()->json([
            'linked_accounts' => $linkedAccounts,
        ]);
    }
}