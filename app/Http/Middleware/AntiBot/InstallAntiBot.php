<?php

namespace App\Http\Middleware\AntiBot;

use App\Enums\AntiBotDecision;
use App\Enums\AntiBotDenyReason;
use App\Services\AntiBot\ChallengeVerificationResult;
use App\Services\AntiBot\ChallengeVerifier;
use App\Services\AntiBot\RiskAssessment;
use App\Services\AntiBot\RiskContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstallAntiBot extends AbstractAntiBotMiddleware
{
    public function __construct(
        \App\Services\AntiBot\RiskEngine $riskEngine,
        \App\Services\AntiBot\DecisionRecorder $decisionRecorder,
        private readonly ChallengeVerifier $challengeVerifier,
    ) {
        parent::__construct($riskEngine, $decisionRecorder);
    }

    protected function surface(Request $request): string
    {
        return 'install';
    }

    protected function handleEnforced(Request $request, Closure $next, RiskContext $context, RiskAssessment $assessment): Response
    {
        if ($assessment->decision === AntiBotDecision::ALLOW) {
            $this->recordDecision($request, $context, $assessment);

            return $next($request);
        }

        if ($assessment->decision === AntiBotDecision::DENY) {
            $deniedAssessment = $assessment->with(
                denyReason: AntiBotDenyReason::RISK_THRESHOLD_EXCEEDED,
                shadowMode: false,
            );

            return $this->deny($request, $context, $deniedAssessment);
        }

        $token = $this->resolveChallengeToken($request);
        if ($token === null) {
            $challengeRequiredAssessment = $assessment->with(
                denyReason: AntiBotDenyReason::CHALLENGE_REQUIRED,
                shadowMode: false,
            );

            return $this->deny($request, $context, $challengeRequiredAssessment, true);
        }

        $verification = $this->challengeVerifier->verify($request, $context->surface, $token);

        if (! $verification->providerAvailable) {
            $degradedAssessment = $assessment->with(
                decision: AntiBotDecision::DEGRADED_FAIL_CLOSED,
                denyReason: AntiBotDenyReason::PROVIDER_UNAVAILABLE_STRICT_SURFACE,
                shadowMode: false,
            );

            return $this->deny($request, $context, $degradedAssessment, true, $verification);
        }

        if ($verification->successful) {
            $passedAssessment = $assessment->with(
                decision: AntiBotDecision::CHALLENGE_PASSED,
                shadowMode: false,
            );

            $this->recordDecision($request, $context, $passedAssessment, [
                'eventType' => (string) config('anti_bot.audit.challenge_passed_event_type', 'anti_bot.challenge_passed'),
                'statusCode' => 200,
                'meta' => $this->verificationMeta($verification),
            ]);

            return $next($request);
        }

        if ($verification->failureReason === 'invalid_token') {
            $failedAssessment = $assessment->with(
                decision: AntiBotDecision::CHALLENGE_FAILED,
                denyReason: AntiBotDenyReason::CHALLENGE_VERIFICATION_FAILED,
                shadowMode: false,
            );

            return $this->deny($request, $context, $failedAssessment, true, $verification);
        }

        $ambiguousAssessment = $assessment->with(
            decision: AntiBotDecision::DEGRADED_FAIL_CLOSED,
            denyReason: AntiBotDenyReason::POLICY_AMBIGUITY,
            shadowMode: false,
        );

        return $this->deny($request, $context, $ambiguousAssessment, true, $verification);
    }

    private function resolveChallengeToken(Request $request): ?string
    {
        $key = trim((string) config('anti_bot.surfaces.install.challenge_input_key', 'X-Install-Challenge-Token'));
        if ($key === '') {
            return null;
        }

        if (str_starts_with(strtolower($key), 'x-')) {
            $value = $request->header($key);

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        $value = $request->input($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function deny(
        Request $request,
        RiskContext $context,
        RiskAssessment $assessment,
        bool $challengeRequired = false,
        ?ChallengeVerificationResult $verification = null,
    ): Response {
        $eventType = match ($assessment->decision) {
            AntiBotDecision::STEP_UP_REQUIRED => (string) config('anti_bot.audit.challenge_required_event_type', 'anti_bot.challenge_required'),
            AntiBotDecision::CHALLENGE_FAILED => (string) config('anti_bot.audit.challenge_failed_event_type', 'anti_bot.challenge_failed'),
            AntiBotDecision::DEGRADED_FAIL_CLOSED => (string) config('anti_bot.audit.degraded_fail_closed_event_type', 'anti_bot.degraded_fail_closed'),
            default => (string) config('anti_bot.audit.denied_event_type', 'anti_bot.denied'),
        };

        $this->recordDecision($request, $context, $assessment, [
            'eventType' => $eventType,
            'statusCode' => (int) config('anti_bot.surfaces.install.response.status', 403),
            'meta' => array_merge(
                ['challenge_required' => $challengeRequired],
                $this->verificationMeta($verification),
            ),
        ]);

        $payload = [
            'message' => $this->denyMessage($assessment->denyReason),
            'decision' => $assessment->decision->value,
            'deny_reason' => $assessment->denyReason?->value,
            'challenge_required' => $challengeRequired,
        ];

        return response()->json($payload, (int) config('anti_bot.surfaces.install.response.status', 403));
    }

    private function denyMessage(?AntiBotDenyReason $denyReason): string
    {
        $key = $denyReason?->value ?? AntiBotDenyReason::RISK_THRESHOLD_EXCEEDED->value;

        return (string) config("anti_bot.surfaces.install.response.messages.{$key}", 'Installer anti-bot request denied.');
    }

    private function verificationMeta(?ChallengeVerificationResult $verification): array
    {
        if ($verification === null) {
            return [];
        }

        return array_filter([
            'challenge_provider_available' => $verification->providerAvailable,
            'challenge_failure_reason' => $verification->failureReason,
            'challenge_latency_ms' => $verification->latencyMs,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function recordDecision(Request $request, RiskContext $context, RiskAssessment $assessment, array $options = []): void
    {
        app(\App\Services\AntiBot\DecisionRecorder::class)->record(
            request: $request,
            context: $context,
            assessment: $assessment,
            eventType: $options['eventType'] ?? null,
            statusCode: $options['statusCode'] ?? 200,
            meta: $options['meta'] ?? [],
        );
    }
}
