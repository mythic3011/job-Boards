<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable HTTP Response Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable HTTP response logging. When enabled, all HTTP
    | responses will be logged with their status code, duration, and metadata.
    |
    */

    'enabled' => env('HTTP_LOGGING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Log Successful Responses
    |--------------------------------------------------------------------------
    |
    | Log 2xx successful responses. You may want to disable this in
    | production to reduce log volume if you only care about errors.
    |
    */

    'log_success' => env('HTTP_LOGGING_SUCCESS', true),

    /*
    |--------------------------------------------------------------------------
    | Log Redirects
    |--------------------------------------------------------------------------
    |
    | Log 3xx redirect responses.
    |
    */

    'log_redirects' => env('HTTP_LOGGING_REDIRECTS', true),

    /*
    |--------------------------------------------------------------------------
    | Slow Request Threshold (milliseconds)
    |--------------------------------------------------------------------------
    |
    | Requests taking longer than this threshold will be flagged as slow
    | and include a performance warning in the logs.
    |
    */

    'slow_request_threshold' => env('HTTP_LOGGING_SLOW_THRESHOLD', 1000),

    /*
    |--------------------------------------------------------------------------
    | Exclude Paths
    |--------------------------------------------------------------------------
    |
    | Paths that should not be logged. Useful for health checks, polling
    | endpoints, or other high-frequency requests that would clutter logs.
    |
    */

    'exclude_paths' => [
        'up',           // Laravel health check
        'livewire/*',   // Livewire requests (already logged by Livewire)
    ],

];
