<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware (runs on all requests)
        $middleware->web(prepend: [
            \App\Http\Middleware\RequestId::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleSuspiciousUserAgent::class, // Replaced BlockBadUserAgent
            \App\Http\Middleware\LogHttpResponse::class, // Log all HTTP responses
        ]);

        // Register middleware aliases
        $middleware->alias([
            'request.id' => \App\Http\Middleware\RequestId::class,
            'setup.not.completed' => \App\Http\Middleware\EnsureSetupNotCompleted::class,
            'setup.completed' => \App\Http\Middleware\EnsureSetupCompleted::class,
            'hide.admin' => \App\Http\Middleware\HideAdminRoutes::class,
            'admin.2fa' => \App\Http\Middleware\RequireAdminTwoFactor::class,
        ]);

        // Register Spatie Permission middleware aliases
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Log permission denied (403) events
        $exceptions->renderable(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->user()) {
                $auditLogger = app(\App\Services\AuditLogger::class);
                $auditLogger->logRequestEvent(
                    eventType: 'permission_denied',
                    request: $request,
                    statusCode: 403,
                    meta: [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ]
                );
            }
        });

        // In development/local, Laravel will show detailed error pages automatically
        // In production, custom error pages will be used
        // No need for empty exception handler
    })->create();
