<?php

namespace Tests\Feature\Application;

use App\Enums\ApplicationDecisionOutcome;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\ApplicationService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class ApplicationReviewContextTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createSettingsTable();
        $this->createPermissionTables();
        $this->createJobPostingsTable();
        $this->createApplicationsTable();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        DB::table('permissions')->insert([
            'name' => 'manage applications',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_company_approve_redirects_back_to_scoped_queue_when_job_idcode_provided(): void
    {
        [$company, $job, $application] = $this->makeOwnedApplicationFixture();
        $this->grantPermission($company, 'manage applications');

        $service = Mockery::mock(ApplicationService::class);
        $service->shouldReceive('approve')
            ->once()
            ->withArgs(fn (Application $candidate, ?string $message): bool => $candidate->is($application) && $message === 'ok')
            ->andReturn(ApplicationDecisionOutcome::UPDATED);
        $this->app->instance(ApplicationService::class, $service);

        $this->actingAs($company)
            ->withBrowser()
            ->post(route('applications.approve', $application->idcode), [
                'decision_message' => 'ok',
                'job_idcode' => $job->idcode,
            ])
            ->assertRedirect(route('my.applications.index', ['jobIdcode' => $job->idcode]));
    }

    public function test_company_reject_redirects_back_to_scoped_queue_when_job_idcode_provided(): void
    {
        [$company, $job, $application] = $this->makeOwnedApplicationFixture();
        $this->grantPermission($company, 'manage applications');

        $service = Mockery::mock(ApplicationService::class);
        $service->shouldReceive('reject')
            ->once()
            ->withArgs(fn (Application $candidate, ?string $message): bool => $candidate->is($application) && $message === 'no')
            ->andReturn(ApplicationDecisionOutcome::UPDATED);
        $this->app->instance(ApplicationService::class, $service);

        $this->actingAs($company)
            ->withBrowser()
            ->post(route('applications.reject', $application->idcode), [
                'decision_message' => 'no',
                'job_idcode' => $job->idcode,
            ])
            ->assertRedirect(route('my.applications.index', ['jobIdcode' => $job->idcode]));
    }

    public function test_application_show_threads_job_scope_into_back_links_and_decision_forms(): void
    {
        [$company, $job, $application] = $this->makeOwnedApplicationFixture();

        $this->actingAs($company)
            ->withBrowser()
            ->get(route('applications.show', $application->idcode).'?jobIdcode='.$job->idcode)
            ->assertOk()
            ->assertSee(route('my.applications.index', ['jobIdcode' => $job->idcode]), false)
            ->assertSee('name="job_idcode"', false)
            ->assertSee('value="'.$job->idcode.'"', false);
    }

    /**
     * @return array{0: User, 1: JobPosting, 2: Application}
     */
    private function makeOwnedApplicationFixture(): array
    {
        $company = User::factory()->company()->create();
        $job = JobPosting::factory()->for($company, 'companyUser')->create();
        $application = Application::factory()->for($job, 'jobPosting')->create();

        return [$company, $job, $application];
    }

    private function grantPermission(User $user, string $permission): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', $permission)
            ->where('guard_name', 'web')
            ->value('id');

        DB::table('model_has_permissions')->insert([
            'permission_id' => $permissionId,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
