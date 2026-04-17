<?php

namespace App\Http\Controllers\Auth;

use App\Models\Setting;
use Laravel\Fortify\Http\Requests\LoginRequest;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as FortifyAuthenticatedSessionController;

class AuthenticatedSessionController extends FortifyAuthenticatedSessionController
{
    /**
     * Handle an incoming authentication request - check maintenance mode for non-admins.
     *
     * @param  \Laravel\Fortify\Http\Requests\LoginRequest  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function store(LoginRequest $request)
    {
        // First, call parent store method to authenticate the user
        $response = parent::store($request);

        // Check if maintenance mode is enabled and user is now authenticated
        if (auth()->check() && Setting::getBool('maintenance_mode', false)) {
            // If authenticated user is NOT an admin, show maintenance page and logout
            if (!auth()->user()->isAdmin()) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return response()->view('errors.maintenance', [], 503);
            }
        }

        // Admin or normal operation - return normal response
        return $response;
    }
}
