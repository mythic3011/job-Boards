<?php

namespace App\Enums;

enum AntiBotDecision: string
{
    case ALLOW = 'allow';
    case STEP_UP_REQUIRED = 'step_up_required';
    case CHALLENGE_PASSED = 'challenge_passed';
    case CHALLENGE_FAILED = 'challenge_failed';
    case DENY = 'deny';
    case BYPASS_ALLOWLIST = 'bypass_allowlist';
    case DEGRADED_FAIL_CLOSED = 'degraded_fail_closed';
}
