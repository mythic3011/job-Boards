<?php

return [
    'max_request_bytes' => (int) env('CANONICAL_AUDIT_MAX_REQUEST_BYTES', 8192),
    'max_clock_skew_seconds' => (int) env('CANONICAL_AUDIT_MAX_CLOCK_SKEW_SECONDS', 300),
    'callers' => [
        env('CANONICAL_AUDIT_AUTH_SERVICE_KEY_ID', 'auth-service') => [
            'secret' => env('CANONICAL_AUDIT_AUTH_SERVICE_SECRET'),
            'source' => 'auth-service',
            'caller_identity' => 'auth-service',
            'allowed_ips' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('CANONICAL_AUDIT_AUTH_SERVICE_ALLOWED_IPS', ''))
            ))),
            'allowed_cidrs' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('CANONICAL_AUDIT_AUTH_SERVICE_ALLOWED_CIDRS', ''))
            ))),
        ],
    ],
];
