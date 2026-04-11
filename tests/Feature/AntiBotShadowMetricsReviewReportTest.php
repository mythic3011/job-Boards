<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\AntiBot\ShadowMetricsReviewReportBuilder;
use Illuminate\Support\Str;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class AntiBotShadowMetricsReviewReportTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createAuditLogsTable();
    }

    public function test_review_report_builder_produces_review_ready_sections_questions_and_allowed_conclusion_types(): void
    {
        $this->storeShadowLog(surface: 'login', decision: 'allow', thresholdState: 'none', signals: ['header_anomaly'], pendingState: 'missing', pendingExpected: false, pendingFlow: false);
        $this->storeShadowLog(surface: 'login', decision: 'step_up_required', thresholdState: 'step_up_required', signals: ['failed_attempt_history'], pendingState: 'missing', pendingExpected: false, pendingFlow: false);
        $this->storeShadowLog(surface: 'two_factor', decision: 'step_up_required', thresholdState: 'step_up_required', signals: ['failed_attempt_history', 'behavior_timing'], pendingState: 'valid', pendingExpected: true, pendingFlow: true, limiterKeysHit: ['pending_login:ip']);
        $this->storeShadowLog(surface: 'two_factor', decision: 'deny', thresholdState: 'deny_required', signals: ['failed_attempt_history', 'header_anomaly'], pendingState: 'valid', pendingExpected: true, pendingFlow: true, limiterKeysHit: ['pending_login:session']);

        $report = app(ShadowMetricsReviewReportBuilder::class)->build(AuditLog::query()->orderBy('occurred_at')->get());

        $this->assertArrayHasKey('status_summary', $report);
        $this->assertArrayHasKey('sections', $report);
        $this->assertArrayHasKey('go_no_go', $report);
        $this->assertArrayHasKey('final_conclusion', $report);

        $this->assertSame(
            ['login', 'two_factor', 'pending_login_flow', 'pending_state_quality'],
            array_keys($report['sections']),
        );

        $this->assertSame('evidence_only', $report['status_summary']['review_scope']);
        $this->assertTrue($report['status_summary']['shadow_mode_only']);
        $this->assertSame(4, $report['status_summary']['requests_observed']);

        $this->assertArrayHasKey('login_enforcement_design', $report['go_no_go']);
        $this->assertArrayHasKey('two_factor_separate_policy', $report['go_no_go']);
        $this->assertArrayHasKey('pending_state_noise', $report['go_no_go']);

        $this->assertIsArray($report['go_no_go']['login_enforcement_design']['evidence']);
        $this->assertIsArray($report['go_no_go']['two_factor_separate_policy']['evidence']);
        $this->assertIsArray($report['go_no_go']['pending_state_noise']['evidence']);

        $this->assertContains(
            $report['final_conclusion'],
            [
                'login enforcement design is not justified yet',
                'login enforcement design is justified for further exploration',
                'two-factor needs a separate design review before any enforcement proposal',
                'pending-state noise must be reduced before enforcement design is credible',
            ],
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
            'method' => 'POST',
            'path' => '/test',
            'status_code' => 200,
            'meta' => [
                'surface' => $surface,
                'decision' => $decision,
                'risk_bucket' => 'medium',
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
}
