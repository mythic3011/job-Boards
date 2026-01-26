<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();

        // If there's an intended URL, redirect there first
        if ($request->session()->has('url.intended')) {
            return redirect()->intended($this->getDefaultRedirect($user));
        }

        // Otherwise, redirect based on user role/type
        return redirect()->to($this->getDefaultRedirect($user));
    }

    /**
     * Get the default redirect path based on user role and type.
     */
    private function getDefaultRedirect($user): string
    {
        // Check if user has admin permissions
        if ($user->hasAnyPermission([
            'admin.system.view',
            'admin.users.view',
            'admin.jobs.view',
            'admin.applications.view',
            'admin.settings.view',
        ])) {
            return route('admin.dashboard');
        }

        if ($user->isCompany()) {
            return route('jobs.index');
        }

        if ($user->isIndividual()) {
            return route('applications.index');
        }

        return route('home');
    }
}
