<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $rid = (string) ($request->headers->get('X-Request-ID') ?: Str::uuid());
        $request->attributes->set('request_id', $rid);
        return $next($request);
    }
}
