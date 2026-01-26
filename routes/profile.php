<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Profile Routes
|--------------------------------------------------------------------------
|
| Routes for user profile management (requires authentication).
|
*/

Route::middleware(['auth'])->group(function () {
    // Two-Factor Authentication settings
    Volt::route('/profile/two-factor', 'profile.two-factor')
        ->name('profile.two-factor');
});
