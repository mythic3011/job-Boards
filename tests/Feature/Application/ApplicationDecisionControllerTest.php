<?php

namespace Tests\Feature\Application;

use App\Enums\ApplicationDecisionOutcome;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\ApplicationService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class ApplicationDecisionControllerTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createAuditLogsTable();
        $this->createSettingsTable();
        $this->createPermissionTables();
        $this->createJobPostingsTable();
        $this->createApplicationsTable();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('permissions')->insert([
            ['name' => 'manage applications', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_approve_success_redirect_is_driven_by_service_outcome(): void
    {
        [$company, $application] = $this->makeOwnedApplicationFixture();
        $this->grantPermission($company, 'manage applications');

        $service = Mockery::mock(ApplicationService::class);
        $service->shouldReceive('approve')
            ->once()
            ->withArgs(fn (Application $candidate, ?string $message): bool => $candidate->is($application) && $message === 'Looks good')
            ->andReturn(ApplicationDecisionOutcome::UPDATED);
        $this->app->instance(ApplicationService::class, $service);

        $this->actingAs($company)
            ->withBrowser()
            ->post(route('applications.approve', $application->idcode), [
                'decision_message' => 'Looks good',
            ])
            ->assertRedirect(route('my.applications.index'))
            ->assertSessionHas('success', 'Application approved successfully.');
    }

    public function test_reject_noop_redirect_is_driven_by_service_outcome(): void
    {
        [$company, $application] = $this->makeOwnedApplicationFixture();
        $this->grantPermission($company, 'manage applications');

        $service = Mockery::mock(ApplicationService::class);
        $service->shouldReceive('reject')
            ->once()
            ->withArgs(fn (Application $candidate, ?string $message): bool => $candidate->is($application) && $message === 'No change')
            ->andReturn(ApplicationDecisionOutcome::NOOP_ALREADY_TARGET);
        $this->app->instance(ApplicationService::class, $service);

        $this->from(route('my.applications.index'))
            ->actingAs($company)
            ->withBrowser()
            ->post(route('applications.reject', $application->idcode), [
                'decision_message' => 'No change',
            ])
            ->assertRedirect(route('my.applications.index'))
            ->assertSessionHas('info', 'This application has already been rejected.');
    }

    public function test_invalid_transition_redirect_is_driven_by_service_outcome(): void
    {
        [$company, $application] = $this->makeOwnedApplicationFixture();
        $this->grantPermission($company, 'manage applications');

        $service = Mockery::mock(ApplicationService::class);
        $service->shouldReceive('approve')
            ->once()
            ->withArgs(fn (Application $candidate, ?string $message): bool => $candidate->is($application) && $message === 'Blocked')
            ->andReturn(ApplicationDecisionOutcome::INVALID_TRANSITION);
        $this->app->instance(ApplicationService::class, $service);

        $this->from(route('my.applications.index'))
            ->actingAs($company)
            ->withBrowser()
            ->post(route('applications.approve', $application->idcode), [
                'decision_message' => 'Blocked',
            ])
            ->assertRedirect(route('my.applications.index'))
            ->assertSessionHasErrors(['application']);
    }

    public function test_controller_delegates_same_target_noop_to_service_even_when_model_is_already_approved(): void
    {
        [$company, $application] = $this->makeOwnedApplicationFixture();
        $this->grantPermission($company, 'manage applications');
        $application->status = \App\Enums\ApplicationStatus::APPROVED;
        $application->save();

        $service = Mockery::mock(ApplicationService::class);
        $service->shouldReceive('approve')
            ->once()
            ->withArgs(fn (Application $candidate, ?string $message): bool => $candidate->is($application) && $message === 'No change')
            ->andReturn(ApplicationDecisionOutcome::NOOP_ALREADY_TARGET);
        $this->app->instance(ApplicationService::class, $service);

        $this->from(route('my.applications.index'))
            ->actingAs($company)
            ->withBrowser()
            ->post(route('applications.approve', $application->idcode), [
                'decision_message' => 'No change',
            ])
            ->assertRedirect(route('my.applications.index'))
            ->assertSessionHas('info', 'This application has already been approved.');
    }

    /**
     * @return array{0: User, 1: Application}
     */
    private function makeOwnedApplicationFixture(): array
    {
        $company = $this->createUser([
            'user_type' => 'company',
            'login_id' => 'company_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(6)) . '@example.com',
        ]);

        $applicant = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'applicant_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(6)) . '@example.com',
        ]);

        $job = JobPosting::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'job_' . Str::uuid(),
            'company_user_id' => $company->id,
            'title' => 'Security Engineer',
            'requirement' => 'Own security roadmap',
            'duty' => 'Review applications',
        ]);

        $application = Application::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'app_' . Str::uuid(),
            'job_id' => $job->id,
            'applicant_user_id' => $applicant->id,
            'message' => 'Please review',
            'cv_file_path' => 'cvs/test.pdf',
            'cv_original_name' => 'test.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 1234,
            'cv_sha256' => hash('sha256', 'test'),
        ]);

        return [$company, $application];
    }

    private function createUser(array $attributes): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_' . Str::uuid(),
            'nickname' => 'Test User',
            'password' => Hash::make('StrongPass123!'),
            ...$attributes,
        ]);
    }

    private function grantPermission(User $user, string $permission): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', $permission)
            ->where('guard_name', 'web')
            ->value('id');

        DB::table('model_has_permissions')->updateOrInsert([
            'permission_id' => $permissionId,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
