<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()'
        );

        // if https enabled, add HSTS header
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Allow Vite dev server in development
        $isDevelopment = app()->environment('local', 'development');
        $viteDevServer = $isDevelopment 
            ? ' http://localhost:5173 ws://localhost:5173' 
            : '';

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'" . $viteDevServer,
            "style-src 'self' 'unsafe-inline'" . $viteDevServer,
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'" . $viteDevServer,
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        // Only add upgrade-insecure-requests in production (blocks http://localhost:5173)
        if (!$isDevelopment) {
            $csp[] = "upgrade-insecure-requests";
        }

        $response->headers->set('Content-Security-Policy', implode('; ', $csp));

        return $response;
    }
}
