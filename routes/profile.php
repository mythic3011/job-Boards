<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Profile Routes
|--------------------------------------------------------------------------
|
| Routes for user profile management (requires authentication).
|
*/

Route::middleware(['auth', 'maintenance.check'])->group(function () {
    // Two-Factor Authentication settings
    Route::get('/profile/two-factor', \App\Livewire\Profile\TwoFactor::class)->name('profile.two-factor');

    Route::middleware('registration.active')->group(function () {
        // Profile management
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile/image', [ProfileController::class, 'deleteProfileImage'])->name('profile.image.delete');

        // Password management
        Route::get('/profile/password', [ProfileController::class, 'showPasswordForm'])
            ->middleware(\App\Http\Middleware\RequireTwoFactorEnabled::class)
            ->name('profile.password');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
            ->middleware(\App\Http\Middleware\RequireTwoFactorEnabled::class)
            ->name('profile.password.update');
    });
});
