<?php

use App\Http\Controllers\BotFingerprintController;
use App\Http\Middleware\CheckMaintenanceMode;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::post('/api/bot/fp-log', [BotFingerprintController::class, 'store'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([
        \App\Http\Middleware\HoneypotProtection::class,
        VerifyCsrfToken::class,
        CheckMaintenanceMode::class,
    ])
    ->name('bot.fp-log');
