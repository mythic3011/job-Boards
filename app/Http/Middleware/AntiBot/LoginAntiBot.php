<?php

namespace App\Http\Middleware\AntiBot;

use Illuminate\Http\Request;

class LoginAntiBot extends AbstractAntiBotMiddleware
{
    private const ROUTE_SURFACES = [
        'login' => 'login',
        'login.store' => 'login',
        'two-factor.login' => 'two_factor',
        'two-factor.login.store' => 'two_factor',
    ];

    protected function shouldHandle(Request $request): bool
    {
        return array_key_exists((string) $request->route()?->getName(), self::ROUTE_SURFACES);
    }

    protected function modeKey(Request $request): string
    {
        return 'login';
    }

    protected function surface(Request $request): string
    {
        return self::ROUTE_SURFACES[(string) $request->route()?->getName()] ?? 'login';
    }
}
