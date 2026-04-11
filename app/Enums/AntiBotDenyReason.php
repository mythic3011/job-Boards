<?php

namespace App\Enums;

enum AntiBotDenyReason: string
{
    case CHALLENGE_REQUIRED = 'challenge_required';
    case CHALLENGE_VERIFICATION_FAILED = 'challenge_verification_failed';
    case RISK_THRESHOLD_EXCEEDED = 'risk_threshold_exceeded';
    case MAINTENANCE_POLICY_BLOCK = 'maintenance_policy_block';
    case PROVIDER_UNAVAILABLE_STRICT_SURFACE = 'provider_unavailable_strict_surface';
    case INVALID_CHALLENGE_TOKEN = 'invalid_challenge_token';
    case ALLOWLIST_REQUIRED = 'allowlist_required';
    case POLICY_AMBIGUITY = 'policy_ambiguity';
}
