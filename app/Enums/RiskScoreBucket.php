<?php

namespace App\Enums;

enum RiskScoreBucket: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
}
