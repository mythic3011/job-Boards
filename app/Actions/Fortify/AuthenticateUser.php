<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;

class AuthenticateUser
{
    public function __construct(
        private readonly AuthenticationService $authService
    ) {
    }

    /**
     * Attempt to authenticate the user using the authentication service.
     */
    public function __invoke(Request $request): ?User
    {
        $username = $request->input(Fortify::username());
        $password = $request->input('password');

        return $this->authService->authenticate($request, $username, $password);
    }
}
