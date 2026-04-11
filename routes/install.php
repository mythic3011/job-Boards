<?php

use App\Http\Controllers\InstallController;
use App\Http\Middleware\EnsureSetupNotCompleted;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Installation Routes
|--------------------------------------------------------------------------
|
| These routes handle the initial setup and installation of the application.
| They are only accessible when the setup is not completed.
|
*/

Route::middleware([
    EnsureSetupNotCompleted::class,
    'anti-bot.install',
])->group(function () {
    Route::get('/install/status', [InstallController::class, 'status'])
        ->name('install.status');

    Route::get('/install', [InstallController::class, 'index'])
        ->name('install.index');

    Route::post('/install/checks', [InstallController::class, 'checks'])
        ->name('install.checks');

    Route::post('/install/complete', [InstallController::class, 'complete'])
        ->name('install.complete');
});
