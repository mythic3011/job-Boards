<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class DashboardServiceVisibilityTest extends TestCase
{
    use UsesInMemorySqlite;

    private DashboardService $dashboardService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createAuditLogsTable();
        $this->createJobPostingsTable();
        $this->createApplicationsTable();

        $this->dashboardService = app(DashboardService::class);
        $this->dashboardService->clearCache();
    }

    protected function tearDown(): void
    {
        $this->dashboardService->clearCache();

        parent::tearDown();
    }

    public function test_dashboard_service_counts_bot_honeypot_and_route_security_events_as_suspicious(): void
    {
        foreach ([
            'suspicious_user_agent',
            'bot_fingerprint_probe',
            'honeypot.triggered',
            'security.route_probe',
            'security.route_scan_detected',
            'security.unauth_access',
        ] as $eventType) {
            AuditLog::factory()->create([
                'event_type' => $eventType,
                'occurred_at' => now(),
            ]);
        }

        AuditLog::factory()->create([
            'event_type' => 'login_success',
            'occurred_at' => now(),
        ]);

        AuditLog::factory()->create([
            'event_type' => 'bot_fingerprint_probe',
            'occurred_at' => now()->subDay(),
        ]);

        $stats = $this->dashboardService->getStats();

        $this->assertSame(6, $stats['suspicious_today']);
    }

    public function test_dashboard_service_recent_activity_includes_bot_and_honeypot_events(): void
    {
        $recentEvents = [
            ['event_type' => 'bot_fingerprint_probe', 'occurred_at' => now()->subSeconds(10)],
            ['event_type' => 'honeypot.triggered', 'occurred_at' => now()->subSeconds(20)],
            ['event_type' => 'setup.completed', 'occurred_at' => now()->subSeconds(30)],
            ['event_type' => 'login_success', 'occurred_at' => now()->subSeconds(40)],
            ['event_type' => 'login_failed', 'occurred_at' => now()->subSeconds(50)],
            ['event_type' => 'user.created', 'occurred_at' => now()->subSeconds(60)],
            ['event_type' => 'job.created', 'occurred_at' => now()->subSeconds(70)],
            ['event_type' => 'application.created', 'occurred_at' => now()->subSeconds(80)],
            ['event_type' => 'application.approved', 'occurred_at' => now()->subSeconds(90)],
        ];

        foreach ($recentEvents as $attributes) {
            AuditLog::factory()->create($attributes);
        }

        $activity = $this->dashboardService->getRecentActivity();

        $this->assertCount(8, $activity);
        $this->assertTrue($activity->pluck('event_type')->contains('bot_fingerprint_probe'));
        $this->assertTrue($activity->pluck('event_type')->contains('honeypot.triggered'));
        $this->assertFalse($activity->pluck('event_type')->contains('application.approved'));
    }

    public function test_dashboard_service_returns_aggregate_core_counts_for_users_jobs_and_applications(): void
    {
        $baseline = $this->dashboardService->getStats();
        $this->dashboardService->clearCache();

        $company = User::factory()->create([
            'user_type' => 'company',
            'created_at' => now(),
        ]);
        User::factory()->create([
            'user_type' => 'individual',
            'created_at' => now(),
            'locked_until' => now()->addDay(),
        ]);
        $applicant = User::factory()->create([
            'user_type' => 'individual',
            'created_at' => now(),
        ]);

        $job = JobPosting::factory()->for($company, 'companyUser')->create([
            'created_at' => now(),
        ]);

        Application::factory()
            ->for($job, 'jobPosting')
            ->for($applicant, 'applicantUser')
            ->create([
            'status' => 'pending',
        ]);

        $stats = $this->dashboardService->getStats();

        $this->assertSame($baseline['total_users'] + 3, $stats['total_users']);
        $this->assertSame($baseline['total_companies'] + 1, $stats['total_companies']);
        $this->assertSame($baseline['total_individuals'] + 2, $stats['total_individuals']);
        $this->assertSame($baseline['locked_users'] + 1, $stats['locked_users']);
        $this->assertSame($baseline['new_users_today'] + 3, $stats['new_users_today']);
        $this->assertSame($baseline['total_jobs'] + 1, $stats['total_jobs']);
        $this->assertSame($baseline['new_jobs_today'] + 1, $stats['new_jobs_today']);
        $this->assertSame($baseline['total_applications'] + 1, $stats['total_applications']);
        $this->assertSame($baseline['pending_applications'] + 1, $stats['pending_applications']);
    }

    public function test_dashboard_service_shows_setup_completed_once_for_duplicate_canonical_install_events(): void
    {
        $request = Request::create('/install/complete', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);
        $request->attributes->set('request_id', (string) Str::uuid());

        $auditLogger = app(AuditLogger::class);

        $auditLogger->logBusinessEvent(
            eventType: 'setup.completed',
            request: $request,
            targetType: 'system',
            targetIdcode: 'setup',
            meta: ['reason' => 'installation_complete'],
        );
        $auditLogger->logBusinessEvent(
            eventType: 'setup.completed',
            request: $request,
            targetType: 'system',
            targetIdcode: 'setup',
            meta: ['reason' => 'installation_complete'],
        );

        $activity = $this->dashboardService->getRecentActivity();

        $this->assertSame(1, AuditLog::query()->where('event_type', 'setup.completed')->count());
        $this->assertCount(1, $activity->where('event_type', 'setup.completed'));
    }
}
