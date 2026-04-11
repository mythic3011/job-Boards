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
        $hasSettingsTable = $this->hasSettingsTable();

        // Check if setup is already completed
        if ($hasSettingsTable && $this->isSetupCompleted()) {
            return $this->denyAccess($request);
        }

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
        $guardEnabled = config('app.install_guard_enabled');
        if ($guardEnabled === null) {
            $guardEnabled = !app()->environment(['local', 'testing']);
        }

        if (!$guardEnabled) {
            return;
        }

        $allowedIps = array_values(array_filter(array_map('trim', config('app.install_allowed_ips', []))));
        $installToken = trim((string) config('app.install_token', ''));
        $requestIp = (string) $request->ip();
        $providedToken = (string) ($request->query('token') ?? $request->header('X-Install-Token', ''));

        if (!empty($allowedIps) && in_array($requestIp, $allowedIps, true)) {
            return;
        }

        if ($installToken !== '' && hash_equals($installToken, $providedToken)) {
            return;
        }

        $reason = empty($allowedIps) && $installToken === ''
            ? 'Install guard is enabled but no bootstrap allowlist or token is configured.'
            : 'Valid installation bootstrap authorization is required.';

        $this->logSecurityEvent('Install access denied by bootstrap guard', [
            'ip' => $requestIp,
            'user_agent' => $request->userAgent(),
            'guard_enabled' => (bool) $guardEnabled,
            'has_allowed_ips' => !empty($allowedIps),
            'has_install_token' => $installToken !== '',
        ]);

        abort(403, $reason);
    }
}
