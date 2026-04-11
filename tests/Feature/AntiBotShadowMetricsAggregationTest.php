<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\AntiBot\ShadowMetricsAggregator;
use Illuminate\Support\Str;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class AntiBotShadowMetricsAggregationTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createAuditLogsTable();
    }

    public function test_shadow_metrics_aggregation_uses_fixed_schema_and_keeps_pending_expected_missing_visible(): void
    {
        $this->storeShadowLog(surface: 'login', decision: 'allow', thresholdState: 'none', signals: [], pendingState: 'missing', pendingExpected: false, pendingFlow: false);
        $this->storeShadowLog(surface: 'login', decision: 'step_up_required', thresholdState: 'step_up_required', signals: ['invalid_pending_login_state'], pendingState: 'malformed', pendingExpected: false, pendingFlow: true, limiterKeysHit: ['pending_login:ip']);
        $this->storeShadowLog(surface: 'two_factor', decision: 'allow', thresholdState: 'none', signals: ['pending_login_expected_but_missing'], pendingState: 'missing', pendingExpected: true, pendingFlow: true, limiterKeysHit: ['pending_login:session']);
        $this->storeShadowLog(surface: 'two_factor', decision: 'step_up_required', thresholdState: 'step_up_required', signals: ['invalid_pending_login_state'], pendingState: 'expired', pendingExpected: true, pendingFlow: true);
        $this->storeNonShadowLog();

        $report = app(ShadowMetricsAggregator::class)->summarize(AuditLog::query()->orderBy('occurred_at')->get());

        $this->assertSame(['login', 'two_factor', 'pending_flow'], array_keys($report['surfaces']));

        foreach (['login', 'two_factor', 'pending_flow'] as $surface) {
            $this->assertArrayHasKey('requests_total', $report['surfaces'][$surface]);
            $this->assertArrayHasKey('decision_distribution', $report['surfaces'][$surface]);
            $this->assertArrayHasKey('triggered_signals', $report['surfaces'][$surface]);
            $this->assertArrayHasKey('threshold_hit_summary', $report['surfaces'][$surface]);
            $this->assertArrayHasKey('pending_state_quality', $report['surfaces'][$surface]);
            $this->assertArrayHasKey('decision_distribution_by_pending_state', $report['surfaces'][$surface]);
            $this->assertArrayHasKey('pending_login_keyed_limiter_hits', $report['surfaces'][$surface]);
            $this->assertArrayHasKey('pending_login_expected_but_missing', $report['surfaces'][$surface]);
        }

        $this->assertSame(2, $report['surfaces']['login']['requests_total']);
        $this->assertSame(1, $report['surfaces']['login']['decision_distribution']['allow']['count']);
        $this->assertSame(1, $report['surfaces']['login']['decision_distribution']['step_up_required']['count']);

        $this->assertSame(2, $report['surfaces']['two_factor']['requests_total']);
        $this->assertSame(1, $report['surfaces']['two_factor']['pending_login_expected_but_missing']['count']);
        $this->assertSame(0.5, $report['surfaces']['two_factor']['pending_login_expected_but_missing']['rate']);
        $this->assertSame(1, $report['surfaces']['two_factor']['pending_login_keyed_limiter_hits']['count']);

        $this->assertSame(3, $report['surfaces']['pending_flow']['requests_total']);
        $this->assertSame(1, $report['surfaces']['pending_flow']['pending_state_quality']['missing']['count']);
        $this->assertSame(1, $report['surfaces']['pending_flow']['pending_state_quality']['malformed']['count']);
        $this->assertSame(1, $report['surfaces']['pending_flow']['pending_state_quality']['expired']['count']);
        $this->assertSame(1, $report['surfaces']['pending_flow']['decision_distribution_by_pending_state']['missing']['allow']['count']);
        $this->assertSame(1, $report['surfaces']['pending_flow']['decision_distribution_by_pending_state']['malformed']['step_up_required']['count']);
        $this->assertSame(1, $report['surfaces']['pending_flow']['decision_distribution_by_pending_state']['expired']['step_up_required']['count']);
        $this->assertSame(2, $report['surfaces']['pending_flow']['pending_login_keyed_limiter_hits']['count']);
        $this->assertSame(1, $report['surfaces']['pending_flow']['pending_login_expected_but_missing']['count']);
        $this->assertContains(
            'pending_login_expected_but_missing',
            array_column($report['surfaces']['pending_flow']['triggered_signals'], 'signal'),
        );
    }

    private function storeShadowLog(
        string $surface,
        string $decision,
        string $thresholdState,
        array $signals,
        string $pendingState,
        bool $pendingExpected,
        bool $pendingFlow,
        array $limiterKeysHit = [],
    ): void {
        AuditLog::create([
            'id' => (string) Str::uuid(),
            'occurred_at' => now(),
            'request_id' => (string) Str::uuid(),
            'actor_type' => 'guest',
            'event_type' => 'anti_bot.risk_scored',
            'method' => 'GET',
            'path' => '/test',
            'status_code' => 200,
            'meta' => [
                'surface' => $surface,
                'decision' => $decision,
                'risk_bucket' => 'low',
                'signals' => $signals,
                'mode' => 'shadow',
                'shadow_mode' => true,
                'threshold_state' => $thresholdState,
                'pending_login_state' => $pendingState,
                'pending_login_expected' => $pendingExpected,
                'pending_login_flow' => $pendingFlow,
                'limiter_keys_hit' => $limiterKeysHit,
            ],
        ]);
    }

    private function storeNonShadowLog(): void
    {
        AuditLog::create([
            'id' => (string) Str::uuid(),
            'occurred_at' => now(),
            'request_id' => (string) Str::uuid(),
            'actor_type' => 'guest',
            'event_type' => 'anti_bot.challenge_required',
            'method' => 'GET',
            'path' => '/install',
            'status_code' => 403,
            'meta' => [
                'surface' => 'install',
                'decision' => 'step_up_required',
                'shadow_mode' => false,
            ],
        ]);
    }
}
