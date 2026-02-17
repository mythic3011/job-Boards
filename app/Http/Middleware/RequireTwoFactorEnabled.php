<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorEnabled
{
    public function __construct(private readonly TwoFactorService $twoFactorService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$this->twoFactorService->isEnabled($user)) {
            abort(404);
        }

        return $next($request);
    }
}
