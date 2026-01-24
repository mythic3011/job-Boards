<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupNotCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check if settings table exists
        if (!Schema::hasTable('settings')) {
            // Table doesn't exist yet, allow installation
            return $next($request);
        }

        // Check if setup is already completed
        try {
            if (Setting::isSetupCompleted()) {
                // Log install probe attempt (OWASP A09)
                try {
                    app(\App\Services\AuditLogger::class)->logRequestEvent(
                        eventType: 'install_probe',
                        request: $request,
                        statusCode: 302, // Redirect
                    );
                } catch (\Exception $e) {
                    // If logging fails, continue anyway
                    Log::warning('Failed to log install probe', ['error' => $e->getMessage()]);
                }

                return redirect()->route('home')
                    ->with('error', 'Setup has already been completed.');
            }
        } catch (\Exception $e) {
            // Error checking, allow installation to proceed
            Log::debug('Error checking setup status, allowing installation', ['error' => $e->getMessage()]);
        }

        // Additional security: Check IP whitelist or INSTALL_TOKEN
        $allowedIps = config('app.install_allowed_ips', []);
        $installToken = config('app.install_token');

        // If IP whitelist is configured, check it
        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
            Log::warning('Install wizard access from non-whitelisted IP', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(403, 'Installation wizard is only accessible from authorized IP addresses.');
        }

        // If INSTALL_TOKEN is configured, require it in query string
        if ($installToken && $request->query('token') !== $installToken) {
            Log::warning('Install wizard access without valid token', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(403, 'Valid installation token is required. Add ?token=YOUR_TOKEN to the URL.');
        }

        return $next($request);
    }
}
