<?php

namespace Tests\Unit;

use App\Enums\ApplicationDecisionOutcome;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\AuditLogger;
use App\Services\CvFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class ApplicationStateHardeningTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createAuditLogsTable();
        $this->createSettingsTable();
        $this->createJobPostingsTable();
        $this->createApplicationsTable();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_application_status_is_not_mass_assignable(): void
    {
        [$job, $applicant] = $this->makeJobAndApplicant();

        $application = Application::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'app_' . Str::uuid(),
            'job_id' => $job->id,
            'applicant_user_id' => $applicant->id,
            'message' => 'Please review',
            'cv_file_path' => 'cvs/resume.pdf',
            'cv_original_name' => 'resume.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 1234,
            'cv_sha256' => hash('sha256', 'resume'),
            'status' => ApplicationStatus::APPROVED->value,
        ]);

        $this->assertSame(ApplicationStatus::PENDING, $application->fresh()->status);
    }

    public function test_application_creation_without_explicit_status_uses_database_default_pending(): void
    {
        [$job, $applicant] = $this->makeJobAndApplicant();

        $application = Application::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'app_' . Str::uuid(),
            'job_id' => $job->id,
            'applicant_user_id' => $applicant->id,
            'message' => 'Please review',
            'cv_file_path' => 'cvs/resume.pdf',
            'cv_original_name' => 'resume.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 1234,
            'cv_sha256' => hash('sha256', 'resume'),
        ]);

        $this->assertSame(ApplicationStatus::PENDING, $application->fresh()->status);
    }

    public function test_status_schema_contract_keeps_pending_default_in_migration_and_sqlite_helper(): void
    {
        $migrationContents = file_get_contents(base_path('database/migrations/2026_02_11_000000_add_status_to_applications_table.php'));
        $sqliteHelperContents = file_get_contents(base_path('tests/Concerns/UsesInMemorySqlite.php'));

        $this->assertIsString($migrationContents);
        $this->assertIsString($sqliteHelperContents);
        $this->assertStringContainsString("->default('pending')->index()", $migrationContents);
        $this->assertStringContainsString("->string('status')->default('pending')->index()", $sqliteHelperContents);
    }

    public function test_application_status_scopes_accept_strings_and_enums(): void
    {
        $pending = $this->createApplicationWithStatus(ApplicationStatus::PENDING);
        $approved = $this->createApplicationWithStatus(ApplicationStatus::APPROVED);
        $rejected = $this->createApplicationWithStatus(ApplicationStatus::REJECTED);

        $this->assertSame([$pending->id], Application::byStatus('pending')->pluck('id')->all());
        $this->assertSame([$approved->id], Application::approved()->pluck('id')->all());
        $this->assertSame([$rejected->id], Application::byStatus(ApplicationStatus::REJECTED)->pluck('id')->all());
        $this->assertSame([$pending->id], Application::pending()->pluck('id')->all());
        $this->assertSame([$rejected->id], Application::rejected()->pluck('id')->all());
    }

    public function test_direct_status_assignment_still_runs_the_transition_gate(): void
    {
        $application = $this->createApplicationWithStatus(ApplicationStatus::APPROVED);

        $this->expectException(\InvalidArgumentException::class);

        $application->status = ApplicationStatus::PENDING;
    }

    public function test_query_builder_update_can_bypass_model_transition_guards(): void
    {
        $application = $this->createApplicationWithStatus(ApplicationStatus::APPROVED);

        Application::query()
            ->whereKey($application->id)
            ->update(['status' => ApplicationStatus::PENDING->value]);

        $this->assertSame(ApplicationStatus::PENDING, $application->fresh()->status);
    }

    public function test_user_sensitive_fields_are_not_mass_assignable(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_' . Str::uuid(),
            'login_id' => 'user_' . Str::lower(Str::random(6)),
            'nickname' => 'Boundary Test',
            'email' => Str::lower(Str::random(8)) . '@example.test',
            'password' => Hash::make('StrongPass123!'),
            'user_type' => 'individual',
            'locked_until' => now()->addDay(),
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['CODE-ONE'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $user->refresh();

        $this->assertNull($user->locked_until);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_locked_scope_only_returns_users_with_future_locks(): void
    {
        $futureLocked = $this->createUser([
            'login_id' => 'future_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);
        $futureLocked->forceFill(['locked_until' => now()->addMinutes(30)])->save();

        $pastLocked = $this->createUser([
            'login_id' => 'past_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);
        $pastLocked->forceFill(['locked_until' => now()->subMinutes(30)])->save();

        $unlocked = $this->createUser([
            'login_id' => 'open_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);

        $this->assertSame([$futureLocked->id], User::locked()->pluck('id')->all());
        $this->assertTrue($futureLocked->isLocked());
        $this->assertFalse($pastLocked->isLocked());
        $this->assertFalse($unlocked->isLocked());
    }

    public function test_approve_returns_updated_and_persists_the_decision(): void
    {
        $company = $this->createUser([
            'user_type' => 'company',
            'login_id' => 'company_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);
        $application = $this->createApplicationWithStatus(ApplicationStatus::PENDING);
        $application->forceFill(['decision_message_read_at' => now()])->save();

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('logBusinessEvent')->once();

        $service = $this->makeApplicationService($company, $auditLogger);

        $outcome = $service->approve($application, 'Approved after review');

        $application->refresh();

        $this->assertSame(ApplicationDecisionOutcome::UPDATED, $outcome);
        $this->assertSame(ApplicationStatus::APPROVED, $application->status);
        $this->assertSame('Approved after review', $application->decision_message);
        $this->assertNull($application->decision_message_read_at);
    }

    public function test_reject_returns_noop_when_application_already_has_the_target_status(): void
    {
        $company = $this->createUser([
            'user_type' => 'company',
            'login_id' => 'company_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);
        $application = $this->createApplicationWithStatus(ApplicationStatus::REJECTED);

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldNotReceive('logBusinessEvent');

        $service = $this->makeApplicationService($company, $auditLogger);

        $outcome = $service->reject($application, 'Already rejected');

        $application->refresh();

        $this->assertSame(ApplicationDecisionOutcome::NOOP_ALREADY_TARGET, $outcome);
        $this->assertSame(ApplicationStatus::REJECTED, $application->status);
        $this->assertNull($application->decision_message);
    }

    public function test_transition_helper_returns_invalid_transition_without_saving_or_logging(): void
    {
        $company = $this->createUser([
            'user_type' => 'company',
            'login_id' => 'company_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);
        $application = $this->createApplicationWithStatus(ApplicationStatus::APPROVED);

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldNotReceive('logBusinessEvent');

        $service = $this->makeApplicationService($company, $auditLogger);
        $helper = new \ReflectionMethod($service, 'transitionDecision');
        $helper->setAccessible(true);

        $outcome = $helper->invoke($service, $application, ApplicationStatus::PENDING, 'Roll back');

        $application->refresh();

        $this->assertSame(ApplicationDecisionOutcome::INVALID_TRANSITION, $outcome);
        $this->assertSame(ApplicationStatus::APPROVED, $application->status);
        $this->assertNull($application->decision_message);
    }

    public function test_successful_status_change_rolls_back_when_audit_logging_fails(): void
    {
        $company = $this->createUser([
            'user_type' => 'company',
            'login_id' => 'company_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);
        $application = $this->createApplicationWithStatus(ApplicationStatus::PENDING);

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('logBusinessEvent')
            ->once()
            ->andThrow(new \RuntimeException('audit transport failed'));

        $service = $this->makeApplicationService($company, $auditLogger);

        try {
            $service->approve($application, 'Approved after review');
            $this->fail('Expected audit failure to bubble.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('audit transport failed', $exception->getMessage());
        }

        $this->assertSame(ApplicationStatus::PENDING, $application->fresh()->status);
        $this->assertNull($application->fresh()->decision_message);
    }

    private function makeApplicationService(User $actor, AuditLogger $auditLogger): ApplicationService
    {
        $this->actingAs($actor);

        $request = Request::create('/applications/decision', 'POST');
        $request->setUserResolver(fn () => $actor);
        $request->attributes->set('request_id', (string) Str::uuid());

        return new ApplicationService(
            Mockery::mock(CvFileService::class),
            $this->app['auth']->guard(),
            $request,
            $auditLogger,
        );
    }

    private function createApplicationWithStatus(ApplicationStatus $status): Application
    {
        [$job, $applicant] = $this->makeJobAndApplicant();

        $application = Application::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'app_' . Str::uuid(),
            'job_id' => $job->id,
            'applicant_user_id' => $applicant->id,
            'message' => 'Please review',
            'cv_file_path' => 'cvs/resume.pdf',
            'cv_original_name' => 'resume.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 1234,
            'cv_sha256' => hash('sha256', 'resume'),
        ]);

        if ($status !== ApplicationStatus::PENDING) {
            $application->status = $status;
            $application->save();
        }

        return $application->fresh();
    }

    /**
     * @return array{0: JobPosting, 1: User}
     */
    private function makeJobAndApplicant(): array
    {
        $company = $this->createUser([
            'user_type' => 'company',
            'login_id' => 'company_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);
        $applicant = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'applicant_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);

        $job = JobPosting::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'job_' . Str::uuid(),
            'company_user_id' => $company->id,
            'title' => 'Security Engineer',
            'requirement' => 'Investigate incidents',
            'duty' => 'Review applications',
        ]);

        return [$job, $applicant];
    }

    private function createUser(array $attributes): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_' . Str::uuid(),
            'nickname' => 'Test User',
            'password' => Hash::make('StrongPass123!'),
            'user_type' => 'individual',
            ...$attributes,
        ]);
    }
}
