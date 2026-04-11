<?php

namespace App\Services\AntiBot;

use App\Enums\AntiBotDecision;
use App\Enums\AntiBotDenyReason;
use App\Enums\RiskScoreBucket;

readonly class RiskAssessment
{
    /**
     * @param list<string> $signals
     */
    public function __construct(
        public AntiBotDecision $decision,
        public RiskScoreBucket $riskBucket,
        public int $score,
        public array $signals,
        public ?AntiBotDenyReason $denyReason,
        public string $mode,
        public bool $shadowMode,
        public string $thresholdState = 'none',
        public array $limiterKeysHit = [],
    ) {}

    public function with(
        ?AntiBotDecision $decision = null,
        ?AntiBotDenyReason $denyReason = null,
        ?bool $shadowMode = null,
        ?string $thresholdState = null,
        ?array $limiterKeysHit = null,
    ): self {
        return new self(
            decision: $decision ?? $this->decision,
            riskBucket: $this->riskBucket,
            score: $this->score,
            signals: $this->signals,
            denyReason: $denyReason ?? $this->denyReason,
            mode: $this->mode,
            shadowMode: $shadowMode ?? $this->shadowMode,
            thresholdState: $thresholdState ?? $this->thresholdState,
            limiterKeysHit: $limiterKeysHit ?? $this->limiterKeysHit,
        );
    }

    public function toAuditMeta(): array
    {
        $meta = [
            'decision' => $this->decision->value,
            'risk_bucket' => $this->riskBucket->value,
            'risk_score' => $this->score,
            'signals' => $this->signals,
            'mode' => $this->mode,
            'shadow_mode' => $this->shadowMode,
            'threshold_state' => $this->thresholdState,
        ];

        if ($this->denyReason !== null) {
            $meta['deny_reason'] = $this->denyReason->value;
        }

        if ($this->limiterKeysHit !== []) {
            $meta['limiter_keys_hit'] = $this->limiterKeysHit;
        }

        return $meta;
    }
}
