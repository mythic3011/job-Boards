<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure application setup is completed before accessing routes.
 */
class EnsureSetupCompleted extends BaseSetupMiddleware
{
    /**
     * Handle an incoming request.
     * Ensures the application setup is completed before allowing access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if settings table exists
        if (!$this->hasSettingsTable()) {
            return $this->redirectToInstall($request, 'Settings table not found');
        }

        // Check if setup is completed
        if (!$this->isSetupCompleted()) {
            return $this->redirectToInstall($request, 'Setup not completed');
        }

        return $next($request);
    }

    /**
     * Redirect to installation wizard with error message.
     */
    private function redirectToInstall(Request $request, string $reason): Response
    {
        $this->logSecurityEvent('Setup incomplete, redirecting to install', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        return redirect()->route('install.index')
            ->with('error', 'Please complete the installation wizard first.');
    }
}
