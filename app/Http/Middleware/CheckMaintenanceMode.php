<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
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
        // Check if maintenance mode is enabled
        $maintenanceEnabled = Setting::getBool('maintenance_mode', false);

        if ($maintenanceEnabled) {
            // Allow admins to bypass maintenance mode
            if (auth()->check() && auth()->user()->hasRole('admin')) {
                $this->auditLogger->logSecurityEvent(
                    eventType: 'maintenance.admin_bypass',
                    request: $request,
                    userId: auth()->id(),
                    meta: ['path' => $request->path()],
                    statusCode: 200,
                );
                return $next($request);
            }

            // For non-authenticated users, show maintenance page
            if (!auth()->check()) {
                return response()->view('errors.maintenance', [], 503);
            }

            // For authenticated non-admin users, show maintenance modal
            return $next($request);
        }

        // Normal operation when maintenance mode is disabled
        return $next($request);
    }
}
