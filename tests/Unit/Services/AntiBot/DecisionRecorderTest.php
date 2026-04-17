<?php

namespace Tests\Unit\Services\AntiBot;

use App\Enums\AntiBotDecision;
use App\Enums\RiskScoreBucket;
use App\Services\AntiBot\DecisionRecorder;
use App\Services\AntiBot\RiskAssessment;
use App\Services\AntiBot\RiskContext;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DecisionRecorderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_record_returns_true_when_audit_persistence_succeeds(): void
    {
        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('logSecurityEvent')
            ->once();
        $this->app->instance(AuditLogger::class, $auditLogger);

        $result = app(DecisionRecorder::class)->record(
            request: $this->request(),
            context: $this->context(),
            assessment: $this->assessment(),
        );

        $this->assertTrue($result);
    }

    public function test_record_returns_false_and_emits_warning_when_audit_persistence_fails(): void
    {
        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('logSecurityEvent')
            ->once()
            ->andThrow(new RuntimeException('audit store unavailable'));
        $this->app->instance(AuditLogger::class, $auditLogger);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'anti_bot.audit_recording_failed',
                Mockery::on(function (array $context): bool {
                    return ($context['event_type'] ?? null) === 'anti_bot.risk_scored'
                        && ($context['surface'] ?? null) === 'login'
                        && ($context['request_id'] ?? null) === 'f4df7021-60aa-4d87-bdab-d1dca2d1f53a'
                        && ($context['exception_class'] ?? null) === RuntimeException::class;
                })
            );

        $result = app(DecisionRecorder::class)->record(
            request: $this->request(),
            context: $this->context(),
            assessment: $this->assessment(),
        );

        $this->assertFalse($result);
    }

    private function request(): Request
    {
        $request = Request::create('/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);
        $request->attributes->set('request_id', 'f4df7021-60aa-4d87-bdab-d1dca2d1f53a');

        return $request;
    }

    private function context(): RiskContext
    {
        return new RiskContext(
            surface: 'login',
            routeName: 'login.store',
            method: 'POST',
            path: 'login',
            ip: '198.51.100.10',
            secure: false,
            host: 'jobboard.local',
            sessionId: 'session-123',
            userAgent: 'PHPUnit',
            actorUserId: null,
            actorUserIdcode: null,
            pendingLoginExpected: false,
            pendingLoginFlow: false,
            pendingLoginState: RiskContext::PENDING_LOGIN_NOT_APPLICABLE,
            pendingLoginUserId: null,
        );
    }

    private function assessment(): RiskAssessment
    {
        return new RiskAssessment(
            decision: AntiBotDecision::ALLOW,
            riskBucket: RiskScoreBucket::LOW,
            score: 5,
            signals: ['request_seen'],
            denyReason: null,
            mode: 'shadow',
            shadowMode: true,
        );
    }
}
