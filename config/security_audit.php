<?php

return [
    'enabled' => env('SECURITY_AUDIT_ENABLED', true),

    'window_minutes' => env('SECURITY_AUDIT_WINDOW_MINUTES', 2),

    'scan_detected_cooldown_minutes' => env('SECURITY_AUDIT_SCAN_COOLDOWN_MINUTES', 10),

    'probe_statuses' => [403, 404, 405],

    'protected_route_middleware' => [
        'auth',
        'verified',
        'admin.2fa',
        'role:admin',
        'can:',
    ],

    'thresholds' => [
        'attempt_count' => env('SECURITY_AUDIT_THRESHOLD_ATTEMPTS', 20),
        'unique_path_count' => env('SECURITY_AUDIT_THRESHOLD_UNIQUE_PATHS', 12),
        'unauth_protected_hits' => env('SECURITY_AUDIT_THRESHOLD_UNAUTH_PROTECTED', 6),
    ],

    'exclude_paths' => [
        'up',
        'livewire/*',
        'build/*',
        'vendor/*',
        'storage/*',
        'favicon.ico',
        'robots.txt',
    ],

    'high_risk_path_patterns' => [
        '.env',
        '.git*',
        'admin*',
        'wp-*',
        'wp/*',
        'phpmyadmin*',
        'mysql*',
        '*phpinfo*',
        '*config*',
        '*backup*',
    ],
];
