<?php

return [
    'enabled' => env('ANTI_BOT_ENABLED', true),

    'surfaces' => [
        'install' => [
            'mode' => env('ANTI_BOT_INSTALL_MODE', 'shadow'),
            'break_glass_allowlist' => [],
            'challenge_input_key' => env('ANTI_BOT_INSTALL_CHALLENGE_INPUT_KEY', 'X-Install-Challenge-Token'),
            'thresholds' => [
                'step_up' => 40,
                'deny' => 80,
            ],
            'provider' => [
                'timeout_ms' => 3000,
            ],
            'response' => [
                'status' => 403,
                'messages' => [
                    'challenge_required' => 'Installer anti-bot challenge required.',
                    'challenge_verification_failed' => 'Installer anti-bot challenge verification failed.',
                    'provider_unavailable_strict_surface' => 'Installer anti-bot verification unavailable.',
                    'policy_ambiguity' => 'Installer anti-bot verification could not be classified.',
                    'risk_threshold_exceeded' => 'Installer anti-bot request denied.',
                ],
            ],
        ],
        'login' => [
            'mode' => env('ANTI_BOT_LOGIN_MODE', 'shadow'),
            'break_glass_allowlist' => [],
        ],
        'admin' => [
            'mode' => env('ANTI_BOT_ADMIN_MODE', 'shadow'),
            'break_glass_allowlist' => [],
        ],
    ],

    'thresholds' => [
        'medium' => 20,
        'high' => 50,
        'critical' => 80,
        'step_up' => 40,
        'deny' => 80,
    ],

    'audit' => [
        'event_type' => 'anti_bot.risk_scored',
        'break_glass_event_type' => 'anti_bot.bypass_allowlist',
        'challenge_required_event_type' => 'anti_bot.challenge_required',
        'challenge_failed_event_type' => 'anti_bot.challenge_failed',
        'challenge_passed_event_type' => 'anti_bot.challenge_passed',
        'denied_event_type' => 'anti_bot.denied',
        'degraded_fail_closed_event_type' => 'anti_bot.degraded_fail_closed',
    ],
];
