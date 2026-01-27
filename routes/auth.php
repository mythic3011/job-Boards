<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
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

// Custom registration route (bypasses Fortify for now)
Route::post('/register', [RegisterController::class, 'store'])
    ->middleware(['guest'])
    ->name('register.store');

// Override Fortify's password reset email route to require 2FA
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
    ->middleware(['guest'])
    ->name('password.email');

// Override Fortify's password reset route to require 2FA
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
    ->middleware(['guest'])
    ->name('password.update');
