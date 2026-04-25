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
            session()->forget('maintenance_bypass_logged');
            return $next($request);
        }

        if ($request->route()?->getName() === 'login.store') {
            return $this->handleLoginSubmissionDuringMaintenance($request, $next);
        }

        if ($this->isMaintenanceAuthFlowExempt($request)) {
            return $next($request);
        }

        if (! auth()->check()) {
            return $this->maintenanceDeniedResponse($request);
        }

        if ($this->hasMaintenanceBypassPermission(auth()->user())) {
            $this->logBypassOnce($request);
            return $next($request);
        }

        return $this->maintenanceDeniedResponse($request);
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

        return $pendingUser !== null && $this->hasMaintenanceBypassPermission($pendingUser);
    }

    private function handleLoginSubmissionDuringMaintenance(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! auth()->check()) {
            return $response;
        }

        if ($this->hasMaintenanceBypassPermission(auth()->user())) {
            $this->logBypassOnce($request);

            return $response;
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->maintenanceDeniedResponse($request);
    }

    private function hasMaintenanceBypassPermission(mixed $user): bool
    {
        if (! is_object($user) || ! method_exists($user, 'hasPermissionTo')) {
            return false;
        }

        try {
            return (bool) $user->hasPermissionTo('maintenance.bypass');
        } catch (\Throwable) {
            return false;
        }
    }

    private function logBypassOnce(Request $request): void
    {
        if (session()->has('maintenance_bypass_logged')) {
            return;
        }

        $this->auditLogger->logSecurityEvent(
            eventType: 'maintenance.admin_bypass',
            request: $request,
            userId: auth()->user()?->idcode,
            meta: ['path' => $request->path()],
            statusCode: 200,
        );

        session()->put('maintenance_bypass_logged', true);
    }

    private function maintenanceDeniedResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'The system is currently under maintenance. Please try again later.',
            ], 503);
        }

        return response()->view('errors.maintenance', [], 503);
    }
}
