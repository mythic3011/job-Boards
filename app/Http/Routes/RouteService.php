<?php

namespace App\Http\Routes;

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\InstallController;
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
        Route::middleware([
            \App\Http\Middleware\EnsureSetupNotCompleted::class,
            'throttle:10,1',
        ])->group(function () {
            Route::get('/install/status', [InstallController::class, 'status'])
                ->name('install.status');

            Route::get('/install', [InstallController::class, 'index'])
                ->name('install.index');

            Route::post('/install/checks', [InstallController::class, 'checks'])
                ->middleware('throttle:5,1')
                ->name('install.checks');

            Route::post('/install/complete', [InstallController::class, 'complete'])
                ->middleware('throttle:2,10')
                ->name('install.complete');
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
