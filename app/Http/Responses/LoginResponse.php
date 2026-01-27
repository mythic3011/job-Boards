<?php

namespace App\Http\Responses;

use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function __construct(
        private readonly AuthenticationService $authService
    ) {
    }

    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            Log::error('LoginResponse called without authenticated user');
            return redirect()->route('login')->withErrors(['error' => 'Authentication failed']);
        }

        Log::info('User login redirect', [
            'user_id' => $user->id,
            'user_type' => $user->user_type,
            'auth_check' => auth()->check(),
            'auth_id' => auth()->id(),
        ]);

        $redirectPath = $this->authService->getPostLoginRedirect($user);

        return redirect($redirectPath)->with('success', 'Welcome back, ' . $user->nickname . '!');
    }
}
