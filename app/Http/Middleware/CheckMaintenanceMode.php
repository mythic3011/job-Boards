<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Contracts\Auth\StatefulGuard;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    private const LOGIN_EXEMPT_ROUTES = [
        'login',
        'login.store',
    ];

    private const TWO_FACTOR_CHALLENGE_ROUTES = [
        'two-factor.login',
        'two-factor.login.store',
    ];

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maintenanceEnabled = Cache::remember('setting.maintenance_mode', 60, fn () => Setting::getBool('maintenance_mode', false));

        if (! $maintenanceEnabled) {
            return $next($request);
        }

        if ($this->isMaintenanceAuthFlowExempt($request)) {
            return $next($request);
        }

        if (! auth()->check()) {
            return response()->view('errors.maintenance', [], 503);
        }

        if (auth()->user()->isAdmin()) {
            // Log once per session to avoid flooding audit logs on every request
            if (! session()->has('maintenance_bypass_logged')) {
                $this->auditLogger->logSecurityEvent(
                    eventType: 'maintenance.admin_bypass',
                    request: $request,
                    userId: auth()->user()->idcode,
                    meta: ['path' => $request->path()],
                    statusCode: 200,
                );
                session()->put('maintenance_bypass_logged', true);
            }

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'The system is currently under maintenance. Please try again later.',
            ], 503);
        }

        return response()->view('errors.maintenance', [], 503);
    }

    private function isMaintenanceAuthFlowExempt(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if ($routeName === null) {
            return false;
        }

        if (in_array($routeName, self::LOGIN_EXEMPT_ROUTES, true)) {
            return true;
        }

        if (! in_array($routeName, self::TWO_FACTOR_CHALLENGE_ROUTES, true)) {
            return false;
        }

        return $this->hasAdminPendingTwoFactorChallenge($request);
    }

    private function hasAdminPendingTwoFactorChallenge(Request $request): bool
    {
        $loginId = $request->session()->get('login.id');
        if (! is_string($loginId) || $loginId === '') {
            return false;
        }

        $model = app(StatefulGuard::class)->getProvider()->getModel();
        $pendingUser = $model::find($loginId);

        return $pendingUser !== null && $pendingUser->isAdmin();
    }
}
