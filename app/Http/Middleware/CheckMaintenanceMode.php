<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
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

        if (! auth()->check()) {
            return response()->view('errors.maintenance', [], 503);
        }

        if (auth()->user()->hasRole('admin')) {
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
}
