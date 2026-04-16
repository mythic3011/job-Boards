<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HoneypotProtection
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

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
            return $this->reject($request, 'filled_honeypot_field', [
                'field_name' => $honeypotField,
                'field_filled' => true,
                'field_length' => strlen((string) $request->input($honeypotField)),
            ]);
        }

        // Check timing token (minimum time between render and submit)
        $timingToken = $request->input('_timing');
        if (!is_string($timingToken) || $timingToken === '') {
            return $this->reject($request, 'missing_timing_token');
        }

        try {
            $renderTime = decrypt($timingToken);
        } catch (\Throwable) {
            return $this->reject($request, 'invalid_timing_token');
        }

        if (!is_int($renderTime) && !ctype_digit((string) $renderTime)) {
            return $this->reject($request, 'invalid_timing_token');
        }

        $elapsed = time() - (int) $renderTime;
        $minTime = (int) config('honeypot.min_time', 3);
        if ($elapsed < $minTime) {
            return $this->reject($request, 'submission_too_fast', [
                'elapsed' => $elapsed,
                'min_required' => $minTime,
            ]);
        }

        $maxTime = (int) config('honeypot.max_time', 3600);
        if ($elapsed > $maxTime) {
            return $this->reject($request, 'expired_timing_token', [
                'elapsed' => $elapsed,
                'max_allowed' => $maxTime,
            ]);
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

    protected function reject(Request $request, string $reason, array $meta = []): Response
    {
        $this->auditLogger->logRequestEvent(
            eventType: 'honeypot.triggered',
            request: $request,
            statusCode: 200,
            targetType: 'security',
            targetIdcode: 'honeypot',
            meta: array_merge([
                'surface' => $request->path(),
                'reason' => $reason,
            ], $meta)
        );

        return response()->json(['message' => 'Request processed'], 200);
    }
}
