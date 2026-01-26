<?php

use App\Http\Controllers\Auth\PasswordResetController;
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

// Override Fortify's password reset email route to require 2FA
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
    ->middleware(['guest'])
    ->name('password.email');
