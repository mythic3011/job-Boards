<?php

namespace App\Services\AntiBot;

readonly class ChallengeVerificationResult
{
    public function __construct(
        public bool $successful,
        public bool $providerAvailable,
        public ?string $failureReason = null,
        public ?int $latencyMs = null,
    ) {}
}
