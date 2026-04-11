<?php

namespace App\Services\AntiBot;

use Illuminate\Http\Request;

class NullChallengeVerifier implements ChallengeVerifier
{
    public function verify(Request $request, string $surface, ?string $token): ChallengeVerificationResult
    {
        return new ChallengeVerificationResult(
            successful: false,
            providerAvailable: false,
            failureReason: 'provider_unavailable',
            latencyMs: null,
        );
    }
}
