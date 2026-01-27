<?php

use App\Http\Controllers\ApplicationController;
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

    // View specific job
    Volt::route('/jobs/{idcode}', 'jobs.show')
        ->name('jobs.show');

    // Create new job (authenticated users only)
    Volt::route('/jobs/create', 'jobs.create')
        ->middleware(['auth', 'throttle:10,1'])
        ->name('jobs.create');
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

    // Download CV from application
    Route::get('/applications/{idcode}/download-cv', [ApplicationController::class, 'downloadCv'])
        ->middleware('throttle:20,1')
        ->name('applications.download-cv');
});
