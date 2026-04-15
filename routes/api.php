<?php

use App\Http\Controllers\Internal\CanonicalAuditIngestionController;
use Illuminate\Support\Facades\Route;

Route::post('/internal/canonical-audit/events', CanonicalAuditIngestionController::class);
