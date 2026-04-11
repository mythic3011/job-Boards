<?php

namespace App\Services\AntiBot;

use App\Enums\AntiBotDecision;
use App\Enums\RiskScoreBucket;

class RiskEngine
{
    public function assess(RiskContext $context, string $mode = 'shadow'): RiskAssessment
    {
        $score = 0;
        $signals = [];
        $thresholdState = 'none';

        if (in_array($context->surface, ['install', 'login', 'two_factor', 'admin'], true)
            && blank($context->userAgent)) {
            $score += 40;
            $signals[] = 'missing_user_agent';
        }

        if (in_array($context->pendingLoginState, [
            RiskContext::PENDING_LOGIN_MALFORMED,
            RiskContext::PENDING_LOGIN_EXPIRED,
        ], true)) {
            $score += 10;
            $signals[] = 'invalid_pending_login_state';
        }

        if ($context->pendingLoginExpected && $context->pendingLoginState === RiskContext::PENDING_LOGIN_MISSING) {
            $signals[] = 'pending_login_expected_but_missing';
        }

        $bucket = $this->resolveBucket($score);
        $denyThreshold = $this->resolveThreshold($context->surface, 'deny', 80);
        $stepUpThreshold = $this->resolveThreshold($context->surface, 'step_up', 40);

        $decision = AntiBotDecision::ALLOW;

        if ($score >= $denyThreshold) {
            $decision = AntiBotDecision::DENY;
            $thresholdState = 'deny_required';
        } elseif ($score >= $stepUpThreshold) {
            $decision = AntiBotDecision::STEP_UP_REQUIRED;
            $thresholdState = 'step_up_required';
        }

        return new RiskAssessment(
            decision: $decision,
            riskBucket: $bucket,
            score: $score,
            signals: $signals,
            denyReason: null,
            mode: $mode,
            shadowMode: $mode === 'shadow',
            thresholdState: $thresholdState,
        );
    }

    private function resolveThreshold(string $surface, string $key, int $default): int
    {
        $surfaceThreshold = config("anti_bot.surfaces.{$surface}.thresholds.{$key}");

        if (is_numeric($surfaceThreshold)) {
            return (int) $surfaceThreshold;
        }

        return (int) config("anti_bot.thresholds.{$key}", $default);
    }

    private function resolveBucket(int $score): RiskScoreBucket
    {
        $critical = (int) config('anti_bot.thresholds.critical', 80);
        $high = (int) config('anti_bot.thresholds.high', 50);
        $medium = (int) config('anti_bot.thresholds.medium', 20);

        return match (true) {
            $score >= $critical => RiskScoreBucket::CRITICAL,
            $score >= $high => RiskScoreBucket::HIGH,
            $score >= $medium => RiskScoreBucket::MEDIUM,
            default => RiskScoreBucket::LOW,
        };
    }
}
