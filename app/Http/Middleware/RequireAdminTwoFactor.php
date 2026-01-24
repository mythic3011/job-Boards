<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // HideAdminRoutes middleware already ensures user is admin
        // Here we only check 2FA confirmation status
        $enabled = !empty($user->two_factor_secret);
        $confirmed = !empty($user->two_factor_confirmed_at);

        if (!$enabled || !$confirmed) {
            // Allow access to 2FA setup/confirm pages and logout
            $allowedRoutes = [
                'profile.two-factor',
                'logout',
            ];

            $currentRoute = $request->route()?->getName();
            if (!in_array($currentRoute, $allowedRoutes)) {
                return redirect()->route('profile.two-factor')
                    ->with('error', 'Two-factor authentication is required for admin accounts. Please set it up and confirm it now.');
            }
        }

        return $next($request);
    }
}
