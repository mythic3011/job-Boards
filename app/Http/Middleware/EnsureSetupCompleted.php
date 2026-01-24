<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupCompleted
{
    /**
     * Handle an incoming request.
     * Ensures the application setup is completed before allowing access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if settings table exists
        if (!Schema::hasTable('settings')) {
            return $this->redirectToInstall($request);
        }

        // Check if setup is completed
        try {
            if (!Setting::isSetupCompleted()) {
                return $this->redirectToInstall($request);
            }
        } catch (\Exception $e) {
            // Error checking, redirect to install
            return $this->redirectToInstall($request);
        }

        return $next($request);
    }

    /**
     * Redirect to installation wizard with error message.
     */
    private function redirectToInstall(Request $request): Response
    {
        return redirect()->route('install.index')
            ->with('error', 'Please complete the installation wizard first.');
    }
}
