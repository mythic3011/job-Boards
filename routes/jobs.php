<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\JobController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Job Routes
|--------------------------------------------------------------------------
|
| Routes for browsing, viewing, and creating job postings.
|
*/

Route::middleware(\App\Http\Middleware\EnsureSetupCompleted::class)->group(function () {
    // Job listing
    Volt::route('/jobs', 'jobs.index')
        ->name('jobs.index');

    // Create new job (authenticated users only)
    // IMPORTANT: must be defined BEFORE /jobs/{idcode} so "create" is not matched as idcode
    Volt::route('/jobs/create', 'jobs.create')
        ->middleware(['auth', 'throttle:10,1'])
        ->name('jobs.create');

    // Create job (POST). Handles form POST when Livewire is not used or as fallback.
    Route::post('/jobs', [JobController::class, 'store'])
        ->middleware(['auth', 'throttle:10,1'])
        ->name('jobs.store');

    // View specific job
    Volt::route('/jobs/{idcode}', 'jobs.show')
        ->name('jobs.show');
});

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Routes for job applications (requires authentication).
|
*/

Route::middleware(['auth'])->group(function () {
    // List user's applications
    Volt::route('/my-applications', 'applications.index')
        ->middleware('throttle:30,1')
        ->name('my.applications.index');

    // Apply to a job
    Volt::route('/jobs/{jobIdcode}/apply', 'applications.create')
        ->middleware('throttle:3,1')
        ->name('applications.create');

    // Apply to a job (POST fallback)
    Route::post('/jobs/{jobIdcode}/apply', [ApplicationController::class, 'store'])
        ->middleware(['throttle:3,1'])
        ->name('applications.store');

    // View application details
    Volt::route('/applications/{idcode}', 'applications.show')
        ->name('applications.show');

    // Download CV from application
    Route::get('/applications/{idcode}/download-cv', [ApplicationController::class, 'downloadCv'])
        ->middleware('throttle:20,1')
        ->name('applications.download-cv');

    // Approve/Reject application (company only)
    Route::post('/applications/{idcode}/approve', [ApplicationController::class, 'approve'])
        ->middleware('throttle:10,1')
        ->name('applications.approve');

    Route::post('/applications/{idcode}/reject', [ApplicationController::class, 'reject'])
        ->middleware('throttle:10,1')
        ->name('applications.reject');
});