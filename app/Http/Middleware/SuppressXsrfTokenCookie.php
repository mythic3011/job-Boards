<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuppressXsrfTokenCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->removeCookie(
            'XSRF-TOKEN',
            (string) config('session.path', '/'),
            config('session.domain')
        );

        return $response;
    }
}
