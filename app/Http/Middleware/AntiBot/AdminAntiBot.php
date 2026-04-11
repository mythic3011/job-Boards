<?php

namespace App\Http\Middleware\AntiBot;

use Illuminate\Http\Request;

class AdminAntiBot extends AbstractAntiBotMiddleware
{
    protected function surface(Request $request): string
    {
        return 'admin';
    }
}
