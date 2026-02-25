<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HoneypotProtection
{
    /**
     * Paths to protect with honeypot checks.
     */
    protected array $protectedPaths = [
        '/login',
        '/register',
        '/forgot-password',
    ];

    /**
     * Handle an incoming request.
     *
     * Honeypot protection uses hidden fields that humans won't fill but bots will.
     * Also enforces minimum time between form render and submission to catch automated bots.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check POST requests on protected paths
        if (!$request->isMethod('post') || !$this->isProtectedPath($request)) {
            return $next($request);
        }

        // Check honeypot field (should be empty)
        $honeypotField = config('honeypot.field_name', 'website');
        if ($request->filled($honeypotField)) {
            Log::warning('Honeypot triggered', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'honeypot_value' => $request->input($honeypotField),
            ]);

            // Return success to avoid revealing the honeypot
            return response()->json(['message' => 'Request processed'], 200);
        }

        // Check timing token (minimum time between render and submit)
        $timingToken = $request->input('_timing');
        if ($timingToken) {
            try {
                $renderTime = decrypt($timingToken);
                $elapsed = time() - $renderTime;

                $minTime = config('honeypot.min_time', 3); // seconds

                if ($elapsed < $minTime) {
                    Log::warning('Honeypot timing check failed', [
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'url' => $request->fullUrl(),
                        'elapsed' => $elapsed,
                        'min_required' => $minTime,
                    ]);

                    // Return success to avoid revealing the protection
                    return response()->json(['message' => 'Request processed'], 200);
                }
            } catch (\Exception $e) {
                // Invalid timing token - likely tampered
                Log::warning('Honeypot timing token invalid', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl(),
                ]);

                return response()->json(['message' => 'Request processed'], 200);
            }
        }

        return $next($request);
    }

    /**
     * Check if the current request path is protected.
     */
    protected function isProtectedPath(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->protectedPaths as $protectedPath) {
            if ($path === ltrim($protectedPath, '/')) {
                return true;
            }
        }

        return false;
    }
}
