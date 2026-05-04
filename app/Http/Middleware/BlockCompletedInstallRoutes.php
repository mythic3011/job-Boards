<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockCompletedInstallRoutes extends BaseSetupMiddleware
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('install') && ! $request->is('install/*')) {
            return $next($request);
        }

        if (! $this->hasSettingsTable() || ! $this->isSetupCompleted()) {
            return $next($request);
        }

        try {
            $this->auditLogger->logRequestEvent(
                eventType: 'install_probe',
                request: $request,
                statusCode: 404
            );
        } catch (\Throwable) {
            // Keep fail-closed behavior even when audit persistence is unavailable.
        }

        abort(404);
    }
}

