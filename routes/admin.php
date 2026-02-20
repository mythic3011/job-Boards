<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Routes for administrative functions with multi-layer protection.
| Requires authentication, permissions, and rate limiting.
|
*/

Route::middleware([
    'web',
    'auth',
    'throttle:30,1', // Rate limit: 30 requests per minute
])->prefix('admin')->name('admin.')->group(function () {
    
    // Dashboard
    Volt::route('/', 'admin.dashboard')
        ->middleware('permission:admin.system.view')
        ->name('dashboard');

    // User Management
    Volt::route('/users', 'admin.users.index')
        ->middleware('permission:admin.users.view')
        ->name('users.index');

    // Job Management
    Volt::route('/jobs', 'admin.jobs.index')
        ->middleware('permission:admin.jobs.view')
        ->name('jobs.index');

    Volt::route('/jobs/{idcode}/edit', 'admin.jobs.edit')
        ->middleware('permission:admin.jobs.moderate')
        ->name('jobs.edit');

    // Application Management
    Volt::route('/applications', 'admin.applications.index')
        ->middleware('permission:admin.applications.view')
        ->name('applications.index');

    // Audit Logs
    Volt::route('/audit-logs', 'admin.audit-logs')
        ->middleware('permission:admin.system.view')
        ->name('audit-logs.index');

    // System Settings
    Volt::route('/settings', 'admin.settings.index')
        ->middleware('permission:admin.settings.view')
        ->name('settings.index');
});
