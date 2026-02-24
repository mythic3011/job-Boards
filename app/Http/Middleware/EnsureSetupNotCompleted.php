<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to prevent access to install routes when setup is already completed.
 */
class EnsureSetupNotCompleted extends BaseSetupMiddleware
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If settings table doesn't exist yet, allow installation
        if (!$this->hasSettingsTable()) {
            return $next($request);
        }

        // Check if setup is already completed
        if ($this->isSetupCompleted()) {
            return $this->denyAccess($request);
        }

        // Additional security checks
        $this->performSecurityChecks($request);

        return $next($request);
    }

    /**
     * Deny access and log the attempt.
     */
    private function denyAccess(Request $request): Response
    {
        try {
            $this->auditLogger->logRequestEvent(
                eventType: 'install_probe',
                request: $request,
                statusCode: 302
            );
        } catch (\Exception $e) {
            $this->logSecurityEvent('Failed to log install probe', [
                'error' => $e->getMessage()
            ]);
        }

        return redirect()->route('install.gone')->with('install_gone', true);
    }

    /**
     * Perform additional security checks for installation access.
     */
    private function performSecurityChecks(Request $request): void
    {
        // Check IP whitelist if configured
        $allowedIps = config('app.install_allowed_ips', []);
        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
            $this->logSecurityEvent('Install access from non-whitelisted IP', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(403, 'Installation wizard is only accessible from authorized IP addresses.');
        }

        // Check for install token if configured
        $installToken = config('app.install_token');
        if ($installToken && $request->query('token') !== $installToken) {
            $this->logSecurityEvent('Install access without valid token', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(403, 'Valid installation token is required. Add ?token=YOUR_TOKEN to the URL.');
        }
    }
}
