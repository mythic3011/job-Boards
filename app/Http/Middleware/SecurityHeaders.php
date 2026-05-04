<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()'
        );
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('Cache-Control', 'no-store, no-cache, max-age=0, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        // Allow Vite dev server in development
        $isDevelopment = app()->environment('local', 'development');
        $viteDevServer = $isDevelopment && Vite::isRunningHot()
            ? ' http://localhost:5173 ws://localhost:5173'
            : '';

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'" . $viteDevServer,
            "style-src 'self' 'nonce-{$nonce}'" . $viteDevServer,
            "img-src 'self' data:",
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
