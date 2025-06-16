<?php

// app/Http/Controllers/Api/Auth/TwoFactorController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorRequest;
use App\Http\Resources\UserResource;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactorService
    ) {}

    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user->two_factor_enabled) {
            return response()->json([
                'error' => 'Two-factor authentication is already enabled',
            ], 400);
        }

        $result = $user->enableTwoFactor();

        return response()->json([
            'message' => 'Two-factor authentication setup initiated',
            'secret' => $result['secret'],
            'qr_code_url' => $result['qr_code_url'],
            'recovery_codes' => $result['recovery_codes'],
        ]);
    }

    public function confirm(TwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user->confirmTwoFactor($request->code)) {
            return response()->json([
                'message' => 'Two-factor authentication enabled successfully',
                'user' => new UserResource($user->fresh()),
            ]);
        }

        throw ValidationException::withMessages([
            'code' => ['The provided code is invalid'],
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        
        if (!$user->two_factor_enabled) {
            return response()->json([
                'error' => 'Two-factor authentication is not enabled',
            ], 400);
        }

        if ($user->disableTwoFactor($request->password)) {
            return response()->json([
                'message' => 'Two-factor authentication disabled successfully',
                'user' => new UserResource($user->fresh()),
            ]);
        }

        throw ValidationException::withMessages([
            'password' => ['The provided password is incorrect'],
        ]);
    }

    public function verify(TwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if this is a temp token for 2FA verification
        if (!$user->tokenCan('2fa:verify')) {
            return response()->json([
                'error' => 'Invalid token for two-factor verification',
            ], 401);
        }

        $isValid = false;
        
        // Try regular 2FA code first
        if ($user->verifyTwoFactorCode($request->code)) {
            $isValid = true;
        }
        // Try recovery code if regular code fails
        elseif ($user->verifyRecoveryCode($request->code)) {
            $isValid = true;
        }

        if ($isValid) {
            // Delete the temporary token
            $user->currentAccessToken()->delete();
            
            // Create new auth token
            $token = $user->createToken('auth-token')->plainTextToken;
            
            // Update login information
            $user->updateLoginInfo(request()->ip(), request()->userAgent());
            
            // Mark 2FA challenge as complete
            $user->markTwoFactorChallengeComplete();

            return response()->json([
                'message' => 'Two-factor authentication successful',
                'user' => new UserResource($user),
                'token' => $token,
            ]);
        }

        throw ValidationException::withMessages([
            'code' => ['The provided code is invalid'],
        ]);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->two_factor_enabled) {
            return response()->json([
                'error' => 'Two-factor authentication is not enabled',
            ], 400);
        }

        return response()->json([
            'recovery_codes' => $user->getRecoveryCodes(),
        ]);
    }

    public function generateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect'],
            ]);
        }

        if (!$user->two_factor_enabled) {
            return response()->json([
                'error' => 'Two-factor authentication is not enabled',
            ], 400);
        }

        $recoveryCodes = $user->generateNewRecoveryCodes();

        return response()->json([
            'message' => 'New recovery codes generated',
            'recovery_codes' => $recoveryCodes,
        ]);
    }
}