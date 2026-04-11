<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class AntiBotShadowMetricsReviewCommandTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createAuditLogsTable();
    }

    public function test_shadow_review_command_emits_json_report_for_recent_window_only(): void
    {
        Carbon::setTestNow('2026-04-11 12:00:00');

        $this->storeShadowLog(surface: 'login', occurredAt: now()->subHours(2), decision: 'allow');
        $this->storeShadowLog(surface: 'two_factor', occurredAt: now()->subHours(1), decision: 'step_up_required', pendingExpected: true, pendingFlow: true, pendingState: 'valid');
        $this->storeShadowLog(surface: 'login', occurredAt: now()->subHours(30), decision: 'deny');

        $exitCode = Artisan::call('anti-bot:shadow-review', ['--hours' => 24, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('evidence_only', $decoded['status_summary']['review_scope']);
        $this->assertSame(2, $decoded['status_summary']['requests_observed']);
        $this->assertSame(1, $decoded['sections']['login']['requests_total']);
        $this->assertSame(1, $decoded['sections']['two_factor']['requests_total']);
    }

    public function test_shadow_review_command_reports_insufficient_evidence_when_no_shadow_events_exist(): void
    {
        Carbon::setTestNow('2026-04-11 12:00:00');

        $exitCode = Artisan::call('anti-bot:shadow-review', ['--hours' => 24, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, $decoded['status_summary']['requests_observed']);
        $this->assertSame('insufficient_evidence', $decoded['go_no_go']['pending_state_noise']['answer']);
        $this->assertSame('login enforcement design is not justified yet', $decoded['final_conclusion']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function storeShadowLog(
        string $surface,
        Carbon $occurredAt,
        string $decision,
        bool $pendingExpected = false,
        bool $pendingFlow = false,
        string $pendingState = 'missing',
    ): void {
        AuditLog::create([
            'id' => (string) Str::uuid(),
            'occurred_at' => $occurredAt,
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
                'signals' => [],
                'mode' => 'shadow',
                'shadow_mode' => true,
                'threshold_state' => $decision === 'deny' ? 'deny_required' : ($decision === 'step_up_required' ? 'step_up_required' : 'none'),
                'pending_login_state' => $pendingState,
                'pending_login_expected' => $pendingExpected,
                'pending_login_flow' => $pendingFlow,
            ],
        ]);
    }
}
