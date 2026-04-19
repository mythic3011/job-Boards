<?php

namespace App\Enums;

enum ApplicationDecisionOutcome: string
{
    case UPDATED = 'updated';
    case NOOP_ALREADY_TARGET = 'noop_already_target';
    case INVALID_TRANSITION = 'invalid_transition';
}
