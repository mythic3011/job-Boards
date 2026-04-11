<?php

namespace App\Services\AntiBot;

use App\Models\AuditLog;
use Illuminate\Support\Collection;

class ShadowMetricsReviewReportBuilder
{
    public function __construct(
        private readonly ShadowMetricsAggregator $aggregator,
    ) {}

    /**
     * @param iterable<AuditLog> $auditLogs
     * @return array<string, mixed>
     */
    public function build(iterable $auditLogs): array
    {
        $logs = Collection::make($auditLogs)
            ->filter(fn ($log): bool => $log->event_type === 'anti_bot.risk_scored')
            ->filter(fn ($log): bool => (bool) data_get($log->meta, 'shadow_mode', false))
            ->values();

        $summary = $this->aggregator->summarize($logs);
        $surfaces = $summary['surfaces'];

        $goNoGo = [
            'login_enforcement_design' => $this->evaluateLoginEnforcementDesign($surfaces),
            'two_factor_separate_policy' => $this->evaluateTwoFactorSeparatePolicy($surfaces),
            'pending_state_noise' => $this->evaluatePendingStateNoise($surfaces),
        ];

        return [
            'status_summary' => [
                'review_scope' => 'evidence_only',
                'shadow_mode_only' => true,
                'requests_observed' => (int) $logs->count(),
            ],
            'sections' => [
                'login' => $surfaces['login'],
                'two_factor' => $surfaces['two_factor'],
                'pending_login_flow' => $surfaces['pending_flow'],
                'pending_state_quality' => $surfaces['pending_flow']['pending_state_quality'],
            ],
            'go_no_go' => $goNoGo,
            'final_conclusion' => $this->resolveFinalConclusion($goNoGo, $surfaces),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildRecent(int $hours = 24): array
    {
        return $this->build(
            AuditLog::query()
                ->recent($hours)
                ->orderBy('occurred_at')
                ->get()
        );
    }

    /**
     * @param array<string, mixed> $surfaces
     * @return array<string, mixed>
     */
    private function evaluateLoginEnforcementDesign(array $surfaces): array
    {
        $login = $surfaces['login'];
        $pendingFlow = $surfaces['pending_flow'];
        $candidateRate = $this->candidateRate($login);
        $pendingNoiseRate = $this->pendingNoiseRate($pendingFlow);
        $signalCount = count($login['triggered_signals']);

        $justified = $login['requests_total'] > 0
            && $candidateRate > 0.0
            && $pendingNoiseRate < 0.5
            && $signalCount > 0;

        return [
            'answer' => $justified ? 'justified_for_further_exploration' : 'not_justified_yet',
            'evidence' => [
                'requests_total' => $login['requests_total'],
                'candidate_rate' => $candidateRate,
                'pending_noise_rate' => $pendingNoiseRate,
                'top_signals' => array_slice(array_column($login['triggered_signals'], 'signal'), 0, 3),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $surfaces
     * @return array<string, mixed>
     */
    private function evaluateTwoFactorSeparatePolicy(array $surfaces): array
    {
        $login = $surfaces['login'];
        $twoFactor = $surfaces['two_factor'];
        $candidateRateDelta = abs($this->candidateRate($twoFactor) - $this->candidateRate($login));
        $limiterDelta = $twoFactor['pending_login_keyed_limiter_hits']['count'] - $login['pending_login_keyed_limiter_hits']['count'];
        $twoFactorSignals = array_column($twoFactor['triggered_signals'], 'signal');
        $loginSignals = array_column($login['triggered_signals'], 'signal');
        $uniqueTwoFactorSignals = array_values(array_diff($twoFactorSignals, $loginSignals));

        $requiresSeparateTrack = $candidateRateDelta >= 0.2
            || $limiterDelta > 0
            || $uniqueTwoFactorSignals !== [];

        return [
            'answer' => $requiresSeparateTrack ? 'separate_design_review_required' : 'shared_review_still_sufficient',
            'evidence' => [
                'candidate_rate_delta' => $candidateRateDelta,
                'pending_login_keyed_limiter_hit_delta' => $limiterDelta,
                'two_factor_unique_signals' => $uniqueTwoFactorSignals,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $surfaces
     * @return array<string, mixed>
     */
    private function evaluatePendingStateNoise(array $surfaces): array
    {
        $pendingFlow = $surfaces['pending_flow'];
        $requestsObserved = (int) $pendingFlow['requests_total'];
        $noiseRate = $this->pendingNoiseRate($pendingFlow);
        $suspiciousCorrelationCount = $this->signalCountForStates($pendingFlow, ['malformed', 'expired']);

        if ($requestsObserved === 0) {
            $answer = 'insufficient_evidence';
        } else {
            $answer = $noiseRate >= 0.5 && $suspiciousCorrelationCount === 0
                ? 'auth_flow_noise'
                : 'meaningful_abuse_indicator';
        }

        return [
            'answer' => $answer,
            'evidence' => [
                'requests_total' => $requestsObserved,
                'pending_noise_rate' => $noiseRate,
                'malformed_count' => $pendingFlow['pending_state_quality']['malformed']['count'],
                'expired_count' => $pendingFlow['pending_state_quality']['expired']['count'],
                'suspicious_signal_correlation_count' => $suspiciousCorrelationCount,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $goNoGo
     * @param array<string, mixed> $surfaces
     */
    private function resolveFinalConclusion(array $goNoGo, array $surfaces): string
    {
        if (
            $goNoGo['pending_state_noise']['answer'] === 'auth_flow_noise'
            && $this->pendingNoiseRate($surfaces['pending_flow']) >= 0.5
        ) {
            return 'pending-state noise must be reduced before enforcement design is credible';
        }

        if ($goNoGo['two_factor_separate_policy']['answer'] === 'separate_design_review_required') {
            return 'two-factor needs a separate design review before any enforcement proposal';
        }

        if ($goNoGo['login_enforcement_design']['answer'] === 'justified_for_further_exploration') {
            return 'login enforcement design is justified for further exploration';
        }

        return 'login enforcement design is not justified yet';
    }

    /**
     * @param array<string, mixed> $surface
     */
    private function candidateRate(array $surface): float
    {
        return (float) $surface['decision_distribution']['step_up_required']['rate']
            + (float) $surface['decision_distribution']['deny']['rate'];
    }

    /**
     * @param array<string, mixed> $pendingFlow
     */
    private function pendingNoiseRate(array $pendingFlow): float
    {
        return (float) $pendingFlow['pending_state_quality']['malformed']['rate']
            + (float) $pendingFlow['pending_state_quality']['expired']['rate'];
    }

    /**
     * @param array<string, mixed> $pendingFlow
     * @param list<string> $states
     */
    private function signalCountForStates(array $pendingFlow, array $states): int
    {
        $count = 0;

        foreach ($states as $state) {
            $stateSignals = $pendingFlow['decision_distribution_by_pending_state'][$state] ?? null;
            if (! is_array($stateSignals)) {
                continue;
            }

            $count += (int) ($stateSignals['step_up_required']['count'] ?? 0);
            $count += (int) ($stateSignals['deny']['count'] ?? 0);
        }

        return $count;
    }
}
