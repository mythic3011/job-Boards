<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
            \App\Http\Middleware\BlockBadUserAgent::class,
            \App\Http\Middleware\HoneypotProtection::class, // honeypot on login/register/forgot-password
            \App\Http\Middleware\HandleSuspiciousUserAgent::class,
            \App\Http\Middleware\AuditSuspiciousAccess::class,
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
            'registration.active' => \App\Http\Middleware\RequireCompletedRegistration::class,
            'maintenance.check' => \App\Http\Middleware\CheckMaintenanceMode::class,
            'anti-bot.install' => \App\Http\Middleware\AntiBot\InstallAntiBot::class,
            'anti-bot.login' => \App\Http\Middleware\AntiBot\LoginAntiBot::class,
            'anti-bot.admin' => \App\Http\Middleware\AntiBot\AdminAntiBot::class,

            // Register Spatie Permission middleware aliases
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $logAdminPermissionDenied = function ($request, ?string $policy = null): void {
            if (! $request->user()) {
                return;
            }

            $route = $request->route();
            $routeName = $route?->getName();
            $path = (string) $request->path();
            $isAdminRoute = is_string($routeName) && str_starts_with($routeName, 'admin.');

            if (! $isAdminRoute && ! str_starts_with($path, 'admin')) {
                return;
            }

            $resolvedPolicy = $policy;
            if ($resolvedPolicy === null && $route !== null) {
                foreach ($route->gatherMiddleware() as $middlewareName) {
                    if (is_string($middlewareName) && str_starts_with($middlewareName, 'permission:')) {
                        $resolvedPolicy = trim(substr($middlewareName, strlen('permission:')));
                        break;
                    }
                }
            }

            app(\App\Services\AuditLogger::class)->logRequestEvent(
                eventType: 'audit.admin.permission.denied',
                request: $request,
                statusCode: 403,
                targetType: 'admin_route',
                targetIdcode: $routeName ?: $path,
                meta: array_filter([
                    'policy' => $resolvedPolicy,
                    'target_hint' => $path,
                ], static fn ($value) => is_string($value) && $value !== ''),
            );
        };

        // Return 404 for unauthenticated requests to hide protected route existence
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if (! $request->expectsJson()) {
                abort(404);
            }
        });

        // Keep canonical admin permission-denied audit rows narrow to admin routes only.
        $exceptions->renderable(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            $logAdminPermissionDenied($request);
        });

        $exceptions->renderable(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) use ($logAdminPermissionDenied) {
            $logAdminPermissionDenied(
                $request,
                $e->getRequiredPermissions()[0] ?? null,
            );
        });

        // Redirect unexpected exceptions to /error in production
        $exceptions->renderable(function (\Throwable $e, $request) {
            if (! $request->expectsJson() && app()->isProduction()) {
                $skip = [
                    \Illuminate\Auth\AuthenticationException::class,
                    \Illuminate\Auth\Access\AuthorizationException::class,
                    \Spatie\Permission\Exceptions\UnauthorizedException::class,
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
