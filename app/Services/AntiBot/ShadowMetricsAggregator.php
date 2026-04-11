<?php

namespace App\Services\AntiBot;

use Illuminate\Support\Collection;

class ShadowMetricsAggregator
{
    /**
     * @param iterable<\App\Models\AuditLog> $auditLogs
     * @return array<string, mixed>
     */
    public function summarize(iterable $auditLogs): array
    {
        $shadowLogs = Collection::make($auditLogs)
            ->filter(fn ($log): bool => $log->event_type === 'anti_bot.risk_scored')
            ->filter(fn ($log): bool => (bool) data_get($log->meta, 'shadow_mode', false))
            ->filter(fn ($log): bool => in_array(data_get($log->meta, 'surface'), ['login', 'two_factor'], true))
            ->values();

        $surfaces = [
            'login' => $this->emptySurfaceSummary(),
            'two_factor' => $this->emptySurfaceSummary(),
            'pending_flow' => $this->emptySurfaceSummary(),
        ];

        foreach ($shadowLogs as $log) {
            $meta = is_array($log->meta) ? $log->meta : [];
            $surface = (string) ($meta['surface'] ?? '');

            if (isset($surfaces[$surface])) {
                $this->accumulate($surfaces[$surface], $meta);
            }

            if ((bool) ($meta['pending_login_flow'] ?? false)) {
                $this->accumulate($surfaces['pending_flow'], $meta);
            }
        }

        return [
            'surfaces' => [
                'login' => $this->finalizeSurfaceSummary($surfaces['login']),
                'two_factor' => $this->finalizeSurfaceSummary($surfaces['two_factor']),
                'pending_flow' => $this->finalizeSurfaceSummary($surfaces['pending_flow']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySurfaceSummary(): array
    {
        return [
            'requests_total' => 0,
            'decision_distribution' => [
                'allow' => ['count' => 0],
                'step_up_required' => ['count' => 0],
                'deny' => ['count' => 0],
            ],
            'decision_distribution_by_pending_state' => [
                'valid' => $this->emptyDecisionBuckets(),
                'missing' => $this->emptyDecisionBuckets(),
                'malformed' => $this->emptyDecisionBuckets(),
                'expired' => $this->emptyDecisionBuckets(),
            ],
            'triggered_signals' => [],
            'threshold_hit_summary' => [
                'none' => ['count' => 0],
                'step_up_required' => ['count' => 0],
                'deny_required' => ['count' => 0],
            ],
            'pending_state_quality' => [
                'valid' => ['count' => 0],
                'missing' => ['count' => 0],
                'malformed' => ['count' => 0],
                'expired' => ['count' => 0],
            ],
            'pending_login_keyed_limiter_hits' => ['count' => 0],
            'pending_login_expected_but_missing' => ['count' => 0],
        ];
    }

    /**
     * @return array<string, array{count: int}>
     */
    private function emptyDecisionBuckets(): array
    {
        return [
            'allow' => ['count' => 0],
            'step_up_required' => ['count' => 0],
            'deny' => ['count' => 0],
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $meta
     */
    private function accumulate(array &$summary, array $meta): void
    {
        $summary['requests_total']++;

        $decision = (string) ($meta['decision'] ?? '');
        if (isset($summary['decision_distribution'][$decision])) {
            $summary['decision_distribution'][$decision]['count']++;
        }

        $thresholdState = (string) ($meta['threshold_state'] ?? 'none');
        if (isset($summary['threshold_hit_summary'][$thresholdState])) {
            $summary['threshold_hit_summary'][$thresholdState]['count']++;
        }

        $pendingState = (string) ($meta['pending_login_state'] ?? '');
        if (isset($summary['pending_state_quality'][$pendingState])) {
            $summary['pending_state_quality'][$pendingState]['count']++;
        }

        if ($pendingState !== '' && isset($summary['decision_distribution_by_pending_state'][$pendingState][$decision])) {
            $summary['decision_distribution_by_pending_state'][$pendingState][$decision]['count']++;
        }

        foreach ((array) ($meta['limiter_keys_hit'] ?? []) as $limiterKey) {
            if (is_string($limiterKey) && str_contains($limiterKey, 'pending_login')) {
                $summary['pending_login_keyed_limiter_hits']['count']++;
                break;
            }
        }

        foreach ((array) ($meta['signals'] ?? []) as $signal) {
            if (! is_string($signal) || $signal === '') {
                continue;
            }

            $summary['triggered_signals'][$signal] = ($summary['triggered_signals'][$signal] ?? 0) + 1;

            if ($signal === 'pending_login_expected_but_missing') {
                $summary['pending_login_expected_but_missing']['count']++;
            }
        }
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private function finalizeSurfaceSummary(array $summary): array
    {
        $total = max(1, (int) $summary['requests_total']);

        foreach ($summary['decision_distribution'] as &$bucket) {
            $bucket['rate'] = $bucket['count'] / $total;
        }
        unset($bucket);

        foreach ($summary['threshold_hit_summary'] as &$bucket) {
            $bucket['rate'] = $bucket['count'] / $total;
        }
        unset($bucket);

        foreach ($summary['pending_state_quality'] as &$bucket) {
            $bucket['rate'] = $bucket['count'] / $total;
        }
        unset($bucket);

        foreach ($summary['decision_distribution_by_pending_state'] as &$decisionBuckets) {
            $pendingStateTotal = max(1, array_sum(array_column($decisionBuckets, 'count')));

            foreach ($decisionBuckets as &$bucket) {
                $bucket['rate'] = $bucket['count'] / $pendingStateTotal;
            }
            unset($bucket);
        }
        unset($decisionBuckets);

        $summary['pending_login_keyed_limiter_hits']['rate'] = $summary['pending_login_keyed_limiter_hits']['count'] / $total;
        $summary['pending_login_expected_but_missing']['rate'] = $summary['pending_login_expected_but_missing']['count'] / $total;

        $signals = [];
        foreach ($summary['triggered_signals'] as $signal => $count) {
            $signals[] = [
                'signal' => $signal,
                'count' => $count,
                'rate' => $count / $total,
            ];
        }

        usort($signals, function (array $left, array $right): int {
            $countComparison = $right['count'] <=> $left['count'];

            if ($countComparison !== 0) {
                return $countComparison;
            }

            return $left['signal'] <=> $right['signal'];
        });

        $summary['triggered_signals'] = $signals;

        return $summary;
    }
}
