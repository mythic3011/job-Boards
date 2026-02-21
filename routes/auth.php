<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Custom authentication routes that override or extend Laravel Fortify's
| default authentication functionality.
|
*/

// Explicit logout: redirect to login with success message (handles sign out from web.php test-nav and header)
Route::post('/logout', function (Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login')->with('success', 'You have been successfully logged out.');
})->name('logout')->middleware('auth');

// Custom registration route (bypasses Fortify for now)
Route::post('/register', [RegisterController::class, 'store'])
    ->middleware(['guest', 'maintenance.check'])
    ->name('register.store');

// Override Fortify's password reset email route to require 2FA
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
    ->middleware(['guest'])
    ->name('password.email');

// Override Fortify's password reset route to require 2FA
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
    ->middleware(['guest'])
    ->name('password.update');
