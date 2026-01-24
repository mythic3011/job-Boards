<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HideAdminRoutes
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * Handle an incoming request.
     * Returns 404 for non-admin users to hide admin route existence.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('admin')) {
            // Log admin probe attempt (even though we return 404)
            $this->auditLogger->logRequestEvent(
                eventType: 'admin_probe',
                request: $request,
                statusCode: 404,
            );

            abort(404);
        }

        return $next($request);
    }
}
