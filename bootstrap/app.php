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
        // Trust Nginx reverse proxy for X-Forwarded-* headers
        $middleware->trustProxies(at: '*');

        // Global middleware (runs on all requests)
        $middleware->web(prepend: [
            \App\Http\Middleware\RequestId::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\BlockBadUserAgent::class,
            \App\Http\Middleware\HoneypotProtection::class, // honeypot on login/register/forgot-password
            \App\Http\Middleware\HandleSuspiciousUserAgent::class,
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \App\Http\Middleware\LogHttpResponse::class,
        ]);

        // Register middleware aliases
        $middleware->alias([
            // common middleware for app routes
            'request.id' => \App\Http\Middleware\RequestId::class,
            'honeypot' => \App\Http\Middleware\HoneypotProtection::class,
            // install middleware
            'setup.not.completed' => \App\Http\Middleware\EnsureSetupNotCompleted::class,
            'setup.completed' => \App\Http\Middleware\EnsureSetupCompleted::class,
            'hide.admin' => \App\Http\Middleware\HideAdminRoutes::class,
            'admin.2fa' => \App\Http\Middleware\RequireAdminTwoFactor::class,
            '2fa.enabled' => \App\Http\Middleware\RequireTwoFactorEnabled::class,
            'maintenance.check' => \App\Http\Middleware\CheckMaintenanceMode::class,

            // Register Spatie Permission middleware aliases
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return 404 for unauthenticated requests to hide protected route existence
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if (! $request->expectsJson()) {
                abort(404);
            }
        });

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

        // Redirect unexpected exceptions to /error in production
        $exceptions->renderable(function (\Throwable $e, $request) {
            if (! $request->expectsJson() && app()->isProduction()) {
                $skip = [
                    \Illuminate\Auth\AuthenticationException::class,
                    \Illuminate\Auth\Access\AuthorizationException::class,
                    \Symfony\Component\HttpKernel\Exception\HttpException::class,
                    \Illuminate\Validation\ValidationException::class,
                ];

                foreach ($skip as $class) {
                    if ($e instanceof $class) {
                        return null;
                    }
                }

                return redirect()->route('error.page')
                    ->with('error_message', 'An unexpected error occurred. Please try again.');
            }
        });
    })->create();
