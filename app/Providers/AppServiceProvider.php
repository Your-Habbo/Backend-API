<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Set default password rules
        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction()
                ? $rule->mixedCase()
                       ->letters()
                       ->numbers()
                       ->symbols()
                       ->uncompromised()
                : $rule;
        });

        // Configure rate limiting
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        // API rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Login attempts rate limiting
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(3)->by($request->input('login')),
            ];
        });

        // Registration rate limiting
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Password reset rate limiting
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(2)->by($request->ip());
        });

        // Two-factor authentication rate limiting
        RateLimiter::for('2fa', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()),
                Limit::perMinute(3)->by($request->ip()),
            ];
        });

        // OAuth rate limiting
        RateLimiter::for('oauth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Admin actions rate limiting
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // API key creation rate limiting
        RateLimiter::for('api-keys', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // File upload rate limiting
        RateLimiter::for('uploads', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(20)->by($request->user()->id)
                : Limit::perMinute(5)->by($request->ip());
        });

        // Email verification rate limiting
        RateLimiter::for('email-verification', function (Request $request) {
            return Limit::perMinute(2)->by($request->user()?->id ?: $request->ip());
        });
    }
}
