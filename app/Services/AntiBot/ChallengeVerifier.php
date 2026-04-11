<?php

namespace App\Services\AntiBot;

use Illuminate\Http\Request;

interface ChallengeVerifier
{
    public function verify(Request $request, string $surface, ?string $token): ChallengeVerificationResult;
}
