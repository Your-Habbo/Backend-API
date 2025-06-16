<?php
// app/Http/Controllers/Api/Auth/AuthController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
        ]);

        // Assign default role
        $user->assignRole('user');

        // Log security event
        $user->logSecurityEvent('account_created');

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $this->checkRateLimit($request);

        $user = $this->attemptLogin($request);

        if (!$user) {
            $this->handleFailedLogin($request);
            
            throw ValidationException::withMessages([
                'credentials' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'account' => ['Your account has been deactivated.'],
            ]);
        }

        // Handle 2FA if enabled
        if ($user->two_factor_enabled) {
            return $this->handleTwoFactorLogin($user, $request);
        }

        return $this->completeLogin($user, $request);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revoke current token
        $request->user()->currentAccessToken()->delete();
        
        // Log security event
        $user->logSecurityEvent('logout');

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revoke all tokens
        $user->tokens()->delete();
        
        // Log security event
        $user->logSecurityEvent('logout_all_devices');

        return response()->json([
            'message' => 'Logged out from all devices successfully',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revoke current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    protected function attemptLogin(LoginRequest $request): ?User
    {
        $credentials = $request->only(['login', 'password']);
        $loginField = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        
        $user = User::where($loginField, $credentials['login'])->first();
        
        if ($user && Hash::check($credentials['password'], $user->password)) {
            return $user;
        }

        return null;
    }

    protected function handleTwoFactorLogin(User $user, Request $request): JsonResponse
    {
        // Generate a temporary token for 2FA verification
        $tempToken = $user->createToken('temp-2fa-token', ['2fa:verify'], now()->addMinutes(5))->plainTextToken;
        
        // Log security event
        $user->logSecurityEvent('two_factor_challenge_required');

        return response()->json([
            'requires_two_factor' => true,
            'temp_token' => $tempToken,
            'message' => 'Two-factor authentication required',
        ]);
    }

    protected function completeLogin(User $user, Request $request): JsonResponse
    {
        // Update login information
        $user->updateLoginInfo($request->ip(), $request->userAgent());
        
        // Clear rate limiting
        RateLimiter::clear($this->throttleKey($request));
        
        // Create auth token
        $token = $user->createToken('auth-token')->plainTextToken;
        
        // Log security event
        $user->logSecurityEvent('login_successful');

        return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    protected function handleFailedLogin(Request $request): void
    {
        $key = $this->throttleKey($request);
        $attempts = RateLimiter::attempts($key);
        
        // Log failed attempt
        if ($user = User::where('email', $request->login)->orWhere('username', $request->login)->first()) {
            $user->logSecurityEvent('login_failed', [
                'attempts' => $attempts + 1,
                'ip_address' => $request->ip(),
            ]);
        }
        
        RateLimiter::hit($key, 900); // 15 minutes
    }

    protected function checkRateLimit(Request $request): void
    {
        $key = $this->throttleKey($request);
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            
            throw ValidationException::withMessages([
                'throttle' => ["Too many login attempts. Try again in {$seconds} seconds."],
            ]);
        }
    }

    protected function throttleKey(Request $request): string
    {
        return 'login_attempts:' . $request->ip();
    }
}
