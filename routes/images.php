<?php

use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Image Routes
|--------------------------------------------------------------------------
|
| Routes for serving images securely.
|
*/

// Profile images (require authentication)
Route::middleware(['auth', 'registration.active', 'throttle:60,1'])->group(function () {
    Route::get('/images/profile/{path}', [ImageController::class, 'showProfileImage'])
        ->name('images.profile')
        ->where('path', '[A-Za-z0-9_-]+'); // Base64 URL-safe pattern
});

// Public images (rate limited but no authentication required)
Route::middleware(['throttle:120,1'])->group(function () {
    Route::get('/images/public/{path}', [ImageController::class, 'showPublicImage'])
        ->name('images.public')
        ->where('path', '[A-Za-z0-9_-]+'); // Base64 URL-safe pattern
});
