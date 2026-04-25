<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompletedRegistration
{
    /**
     * @var list<string>
     */
    private const ALLOWED_PENDING_ROUTES = [
        'logout',
        'profile.two-factor',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isRegistrationPending()) {
            if ($this->isAllowedPendingRoute($request)) {
                return $next($request);
            }

            $this->rememberPendingDestination($request);

            return redirect()->route('profile.two-factor')
                ->with('error', 'Please complete two-factor setup to finish activating your account.');
        }

        return $next($request);
    }

    private function isAllowedPendingRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName)
            && in_array($routeName, self::ALLOWED_PENDING_ROUTES, true);
    }

    private function rememberPendingDestination(Request $request): void
    {
        if (! $request->isMethod('GET')) {
            return;
        }

        $request->session()->put('registration.pending_intended', $request->fullUrl());
    }
}
