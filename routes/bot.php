<?php

use App\Http\Controllers\BotFingerprintController;
use Illuminate\Support\Facades\Route;

Route::post('/api/bot/fp-log', [BotFingerprintController::class, 'store'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([\App\Http\Middleware\HoneypotProtection::class])
    ->name('bot.fp-log');
