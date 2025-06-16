<?php

// app/Http/Middleware/LogSecurityEvents.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogSecurityEvents
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        $user = $request->user();
        
        // Only log for authenticated users
        if (!$user) {
            return $response;
        }

        // Log security-sensitive actions
        $this->logSecuritySensitiveActions($request, $user, $response);

        return $response;
    }

    protected function logSecuritySensitiveActions(Request $request, $user, Response $response): void
    {
        $method = $request->method();
        $path = $request->path();
        $statusCode = $response->getStatusCode();

        // Define security-sensitive patterns
        $securityPatterns = [
            'password' => '/password/',
            'two_factor' => '/two-factor|2fa/',
            'api_keys' => '/api-keys/',
            'oauth' => '/oauth/',
            'admin' => '/admin/',
            'roles' => '/roles/',
            'permissions' => '/permissions/',
        ];

        foreach ($securityPatterns as $type => $pattern) {
            if (preg_match($pattern, $path)) {
                $eventType = $this->determineEventType($method, $path, $statusCode, $type);
                
                if ($eventType) {
                    $user->logSecurityEvent($eventType, [
                        'method' => $method,
                        'path' => $path,
                        'status_code' => $statusCode,
                        'user_agent' => $request->userAgent(),
                    ]);
                }
                break;
            }
        }
    }

    protected function determineEventType(string $method, string $path, int $statusCode, string $type): ?string
    {
        if ($statusCode >= 400) {
            return null; // Don't log failed requests here
        }

        $eventMap = [
            'password' => [
                'PUT' => 'password_changed',
                'PATCH' => 'password_changed',
                'POST' => 'password_reset_requested',
            ],
            'two_factor' => [
                'POST' => 'two_factor_action',
                'PUT' => 'two_factor_action',
                'DELETE' => 'two_factor_disabled',
            ],
            'api_keys' => [
                'POST' => 'api_key_created',
                'DELETE' => 'api_key_revoked',
            ],
            'oauth' => [
                'POST' => 'oauth_action',
                'DELETE' => 'oauth_unlinked',
            ],
            'admin' => [
                'POST' => 'admin_action',
                'PUT' => 'admin_action',
                'PATCH' => 'admin_action',
                'DELETE' => 'admin_action',
            ],
        ];

        return $eventMap[$type][$method] ?? null;
    }
}