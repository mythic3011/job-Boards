<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Base class for setup-related middleware with shared functionality.
 */
abstract class BaseSetupMiddleware
{
    /**
     * Check if setup has been completed.
     */
    protected function isSetupCompleted(): bool
    {
        try {
            if (!Schema::hasTable('settings')) {
                return false;
            }

            return Setting::isSetupCompleted();
        } catch (\Exception $e) {
            Log::debug('Error checking setup status', [
                'error' => $e->getMessage(),
                'middleware' => static::class
            ]);
            return false;
        }
    }

    /**
     * Check if the settings table exists.
     */
    protected function hasSettingsTable(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Exception $e) {
            Log::debug('Error checking settings table', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Log security event.
     */
    protected function logSecurityEvent(string $event, array $context = []): void
    {
        Log::warning($event, array_merge([
            'middleware' => static::class,
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }
}
