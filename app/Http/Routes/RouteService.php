<?php

namespace App\Http\Routes;

use App\Http\Controllers\ApplicationController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/**
 * Service class to organize route definitions.
 * This provides better structure and makes routes easier to maintain.
 */
class RouteService
{
    /**
     * Register all application routes.
     * Install routes must be registered FIRST to avoid conflicts.
     */
    public static function register(): void
    {
        // Install routes MUST be first - before any other routes
        self::registerInstallRoutes();
        
        // Then register all other routes
        self::registerHomeRoute();
        self::registerJobRoutes();
        self::registerApplicationRoutes();
        self::registerProfileRoutes();
        self::registerAdminRoutes();
    }

    /**
     * Register installation wizard routes.
     * These routes are isolated and must be registered first.
     */
    private static function registerInstallRoutes(): void
    {
        // Security: Comprehensive protection for install routes
        Route::middleware([
            \App\Http\Middleware\EnsureSetupNotCompleted::class,
            'throttle:10,1'
        ])->group(function () {

            // Security: Pre-flight check for installation
            Route::get('/install/status', function (\Illuminate\Http\Request $request) {
                $installService = app(\App\Services\InstallService::class);
                $status = $installService->isInstallationAllowed($request);

                return response()->json([
                    'allowed' => $status['allowed'],
                    'issues' => $status['issues'],
                    'timestamp' => now()->timestamp
                ])->header('X-Content-Type-Options', 'nosniff')
                  ->header('X-Frame-Options', 'DENY')
                  ->header('X-XSS-Protection', '1; mode=block');
            })->name('install.status');
            Route::get('/install', function () {
                return view('install.index');
            })->name('install.index');
            
            // Security: API routes for JavaScript install wizard with enhanced security
            Route::post('/install/checks', function (\Illuminate\Http\Request $request) {
                // Security: Additional validation
                $request->validate([
                    'timestamp' => 'required|integer',
                    'session' => 'required|string|max:100'
                ]);

                // Security: Check request age (prevent replay attacks)
                $requestAge = now()->timestamp - $request->timestamp;
                if ($requestAge > 300 || $requestAge < -60) { // 5 minutes max, 1 minute tolerance
                    return response()->json(['error' => 'Request expired'], 400);
                }

                try {
                    $installService = app(\App\Services\InstallService::class);

                    // Security: Log the check attempt
                    app(\App\Services\AuditLogger::class)->logBusinessEvent(
                        eventType: 'install.checks_attempt',
                        request: $request,
                        targetType: 'system',
                        targetIdcode: null,
                        meta: [
                            'session' => $request->session,
                            'ip' => $request->ip(),
                            'user_agent' => $request->userAgent()
                        ]
                    );

                    $checks = $installService->runSystemChecks();

                    return response()->json([
                        'checks' => $checks,
                        'session' => $request->session
                    ])->header('X-Content-Type-Options', 'nosniff')
                      ->header('X-Frame-Options', 'DENY')
                      ->header('X-XSS-Protection', '1; mode=block');

                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Install checks failed', [
                        'error' => $e->getMessage(),
                        'session' => $request->session,
                        'ip' => $request->ip()
                    ]);

                    return response()->json([
                        'checks' => [
                            'database' => false,
                            'storage' => false,
                            'cache' => false,
                            'error' => 'System check failed'
                        ]
                    ], 500);
                }
            })->name('install.checks')->middleware('throttle:5,1');

            Route::post('/install/complete', function (\Illuminate\Http\Request $request) {
                // Security: Comprehensive validation
                $request->validate([
                    'admin_name' => 'required|string|max:255|regex:/^[a-zA-Z\s\-_\.]+$/',
                    'admin_email' => 'required|email:rfc,dns|unique:users,email|max:255',
                    'admin_password' => 'required|string|min:12|max:255|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
                    'admin_password_confirmation' => 'required|string|same:admin_password',
                    'install_demo_data' => 'boolean',
                    'timestamp' => 'required|integer',
                    'session' => 'required|string|max:100'
                ]);

                // Security: Check request age (prevent replay attacks)
                $requestAge = now()->timestamp - $request->timestamp;
                if ($requestAge > 600 || $requestAge < -60) { // 10 minutes max, 1 minute tolerance
                    return response()->json(['error' => 'Request expired'], 400);
                }

                // Security: Check for suspicious patterns
                if (app(\App\Http\Middleware\HandleSuspiciousUserAgent::class)->isSuspicious($request)) {
                    \Illuminate\Support\Facades\Log::warning('Suspicious install attempt blocked', [
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'session' => $request->session
                    ]);

                    return response()->json(['error' => 'Access denied'], 403);
                }

                // Security: Rate limit installation attempts
                $key = 'install_attempts_' . $request->ip();
                $attempts = \Illuminate\Support\Facades\Cache::get($key, 0);
                if ($attempts >= 3) {
                    return response()->json(['error' => 'Too many attempts. Try again later.'], 429);
                }
                \Illuminate\Support\Facades\Cache::put($key, $attempts + 1, now()->addMinutes(30));

                try {
                    $installService = app(\App\Services\InstallService::class);

                    // Security: Log the installation attempt
                    app(\App\Services\AuditLogger::class)->logBusinessEvent(
                        eventType: 'install.complete_attempt',
                        request: $request,
                        targetType: 'system',
                        targetIdcode: null,
                        meta: [
                            'session' => $request->session,
                            'ip' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                            'demo_data' => $request->boolean('install_demo_data')
                        ]
                    );

                    $installService->completeInstallation([
                        'admin_name' => $request->admin_name,
                        'admin_email' => $request->admin_email,
                        'admin_password' => $request->admin_password,
                        'install_demo_data' => $request->boolean('install_demo_data'),
                    ]);

                    // Security: Clear rate limit on success
                    \Illuminate\Support\Facades\Cache::forget($key);

                    return response()->json([
                        'success' => true,
                        'message' => 'Installation completed successfully!',
                        'redirect' => route('login')
                    ])->header('X-Content-Type-Options', 'nosniff')
                      ->header('X-Frame-Options', 'DENY')
                      ->header('X-XSS-Protection', '1; mode=block');

                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Installation failed', [
                        'error' => $e->getMessage(),
                        'session' => $request->session,
                        'ip' => $request->ip(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Installation failed. Please try again.'
                    ], 500);
                }
            })->name('install.complete')->middleware('throttle:2,10'); // Max 2 attempts per 10 minutes
        });
    }

    /**
     * Register home route.
     */
    private static function registerHomeRoute(): void
    {
        Route::get('/', function () {
            if (!\Illuminate\Support\Facades\Schema::hasTable('settings') ||
                !\App\Models\Setting::isSetupCompleted()) {
                return redirect()->route('install.index');
            }

            return view('welcome');
        })->name('home');
    }

    /**
     * Register job-related routes.
     */
    private static function registerJobRoutes(): void
    {
        Route::middleware(\App\Http\Middleware\EnsureSetupCompleted::class)->group(function () {
            Volt::route('/jobs', 'jobs.index')
                ->name('jobs.index');

            Volt::route('/jobs/{idcode}', 'jobs.show')
                ->name('jobs.show');

            Volt::route('/jobs/create', 'jobs.create')
                ->middleware(['auth', 'throttle:10,1'])
                ->name('jobs.create');
        });
    }

    /**
     * Register application-related routes.
     */
    private static function registerApplicationRoutes(): void
    {
        Route::middleware([
            'auth',
            \App\Http\Middleware\EnsureSetupCompleted::class
        ])->group(function () {
            Volt::route('/applications', 'applications.index')
                ->middleware('throttle:30,1')
                ->name('applications.index');

            // OWASP A01: Rate limit application submission (prevent spam + mass uploads)
            Volt::route('/jobs/{jobIdcode}/apply', 'applications.create')
                ->middleware('throttle:3,1')
                ->name('applications.create');

            Route::get('/applications/{idcode}/download-cv', [ApplicationController::class, 'downloadCv'])
                ->middleware('throttle:20,1')
                ->name('applications.download-cv');
        });
    }

    /**
     * Register profile-related routes.
     */
    private static function registerProfileRoutes(): void
    {
        Volt::route('/profile/two-factor', 'profile.two-factor')
            ->middleware([
                'auth',
                \App\Http\Middleware\EnsureSetupCompleted::class
            ])
            ->name('profile.two-factor');
    }

    /**
     * Register admin routes with multi-layer protection.
     */
    private static function registerAdminRoutes(): void
    {
        // OWASP A01: Rate limit admin routes (even 404s) to prevent scanning/probing
        Route::middleware([
            'web',
            \App\Http\Middleware\EnsureSetupCompleted::class,
            'throttle:30,1',   // Rate limit admin routes (30 requests per minute)
            \App\Http\Middleware\HideAdminRoutes::class,      // Non-admin → 404 (but logged as admin_probe)
            \App\Http\Middleware\RequireAdminTwoFactor::class,       // Admin must have confirmed 2FA
        ])->prefix('admin')->name('admin.')->group(function () {
            Volt::route('/', 'admin.dashboard')
                ->middleware('permission:admin.system.view')
                ->name('dashboard');

            Volt::route('/users', 'admin.users.index')
                ->middleware('permission:admin.users.view')
                ->name('users.index');

            Volt::route('/jobs', 'admin.jobs.index')
                ->middleware('permission:admin.jobs.view')
                ->name('jobs.index');

            Volt::route('/applications', 'admin.applications.index')
                ->middleware('permission:admin.applications.view')
                ->name('applications.index');

            Volt::route('/settings', 'admin.settings.index')
                ->middleware('permission:admin.settings.view')
                ->name('settings.index');
        });
    }
}
