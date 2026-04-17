<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class HomeDashboardUiContractTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createSettingsTable();
        $this->createUsersTable();
        $this->createPermissionTables();
        $this->createJobPostingsTable();
        $this->createApplicationsTable();

        Setting::markSetupCompleted();
    }

    public function test_home_view_delegates_to_role_specific_partials(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/welcome.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("home.partials.guest", $contents);
        $this->assertStringContainsString("home.partials.individual", $contents);
        $this->assertStringContainsString("home.partials.company", $contents);
        $this->assertStringContainsString("home.partials.admin", $contents);
    }

    public function test_guest_home_keeps_the_public_landing_surface(): void
    {
        $company = User::factory()->company()->create();
        JobPosting::factory()->for($company, 'companyUser')->count(2)->create();

        $response = $this->withBrowser()->get(route('home'));

        $response->assertOk()
            ->assertSeeText('Find Your Dream Job')
            ->assertSeeText('Recent Job Postings')
            ->assertDontSeeText('Career Dashboard')
            ->assertDontSeeText('Hiring Dashboard')
            ->assertDontSeeText('Admin Control Room');
    }

    public function test_individual_home_surfaces_a_candidate_dashboard(): void
    {
        $individual = User::factory()->individual()->create([
            'nickname' => 'Avery Candidate',
            'two_factor_confirmed_at' => now(),
        ]);
        $company = User::factory()->company()->create([
            'nickname' => 'Northline Studio',
        ]);
        $job = JobPosting::factory()->for($company, 'companyUser')->create([
            'title' => 'Staff Product Designer',
        ]);

        Application::factory()->for($job, 'jobPosting')->for($individual, 'applicantUser')->create([
            'status' => ApplicationStatus::PENDING->value,
        ]);

        $response = $this->actingAs($individual)->withBrowser()->get(route('home'));

        $response->assertOk()
            ->assertSeeText('Career Dashboard')
            ->assertSeeText('Application Pipeline')
            ->assertSeeText('Recent Applications')
            ->assertSeeText('Security Checkpoint')
            ->assertDontSeeText('Find Your Dream Job');
    }

    public function test_company_home_surfaces_a_hiring_dashboard(): void
    {
        $company = User::factory()->company()->create([
            'nickname' => 'Harbor Labs',
        ]);
        $applicant = User::factory()->individual()->create();
        $job = JobPosting::factory()->for($company, 'companyUser')->create([
            'title' => 'Platform Engineer',
        ]);

        Application::factory()->for($job, 'jobPosting')->for($applicant, 'applicantUser')->create([
            'status' => ApplicationStatus::PENDING->value,
        ]);

        $response = $this->actingAs($company)->withBrowser()->get(route('home'));

        $response->assertOk()
            ->assertSeeText('Hiring Dashboard')
            ->assertSeeText('Response Queue')
            ->assertSeeText('Active Listings')
            ->assertSeeText('Operational Checklist')
            ->assertDontSeeText('Find Your Dream Job');
    }

    public function test_admin_home_surfaces_a_control_room_entrypoint(): void
    {
        $admin = $this->createAdminUser('Ops Admin');
        $this->grantPermission($admin, 'admin.system.view');

        $response = $this->actingAs($admin)->withBrowser()->get(route('home'));

        $response->assertOk()
            ->assertSeeText('Admin Control Room')
            ->assertSeeText('Operational Surfaces')
            ->assertSeeText('Open Admin Dashboard')
            ->assertDontSeeText('Find Your Dream Job');
    }

    public function test_admin_home_filters_links_to_the_user_permitted_admin_surfaces(): void
    {
        $admin = $this->createAdminUser('Scoped Admin');
        $this->grantPermission($admin, 'admin.users.view');

        $response = $this->actingAs($admin)->withBrowser()->get(route('home'));

        $response->assertOk()
            ->assertSeeText('Admin Control Room')
            ->assertSeeText('Review Users')
            ->assertSee('data-admin-nav-trigger-summary', false)
            ->assertSee('href="'.route('admin.users.index').'"', false)
            ->assertDontSeeText('Open Admin Dashboard')
            ->assertDontSee('href="'.route('admin.dashboard').'"', false)
            ->assertDontSee('href="'.route('admin.jobs.index').'"', false)
            ->assertDontSee('href="'.route('admin.applications.index').'"', false)
            ->assertDontSee('href="'.route('admin.settings.index').'"', false);
    }

    public function test_admin_home_keeps_dashboard_entrypoint_for_system_scope_even_without_user_scope(): void
    {
        $admin = $this->createAdminUser('Systems Admin');
        $this->grantPermission($admin, 'admin.system.view');

        $response = $this->actingAs($admin)->withBrowser()->get(route('home'));

        $response->assertOk()
            ->assertSeeText('Admin tools')
            ->assertSeeText('Open Admin Dashboard')
            ->assertSee('href="'.route('admin.dashboard').'"', false)
            ->assertDontSee('href="'.route('admin.users.index').'"', false);
    }

    public function test_admin_home_without_admin_permissions_hides_protected_admin_links(): void
    {
        $admin = $this->createAdminUser('Unscoped Admin');

        $response = $this->actingAs($admin)->withBrowser()->get(route('home'));

        $response->assertOk()
            ->assertSeeText('Admin Control Room')
            ->assertSeeText('No admin modules are assigned yet')
            ->assertSeeText('Open Dashboard')
            ->assertDontSee('data-admin-nav-trigger-summary', false)
            ->assertDontSee('href="'.route('admin.dashboard').'"', false)
            ->assertDontSee('href="'.route('admin.users.index').'"', false)
            ->assertDontSee('href="'.route('admin.jobs.index').'"', false)
            ->assertDontSee('href="'.route('admin.applications.index').'"', false)
            ->assertDontSee('href="'.route('admin.settings.index').'"', false);
    }

    private function grantPermission(User $user, string $permission): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => $permission, 'guard_name' => 'web'],
            ['created_at' => now(), 'updated_at' => now()],
        );

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

    private function createAdminUser(string $nickname): User
    {
        $admin = User::factory()->create([
            'nickname' => $nickname,
            'user_type' => 'company',
        ]);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin;
    }

    protected function createJobPostingsTable(): void
    {
        Schema::create('job_postings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('idcode')->unique();
            $table->uuid('company_user_id')->index();
            $table->string('title');
            $table->text('requirement');
            $table->text('duty');
            $table->unsignedInteger('salary_from')->nullable();
            $table->unsignedInteger('salary_to')->nullable();
            $table->timestamps();

            $table->foreign('company_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    protected function createApplicationsTable(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('idcode')->unique();
            $table->uuid('job_id')->index();
            $table->uuid('applicant_user_id')->index();
            $table->text('message')->nullable();
            $table->text('decision_message')->nullable();
            $table->timestamp('decision_message_read_at')->nullable();
            $table->text('cv_file_path');
            $table->string('cv_original_name')->nullable();
            $table->string('cv_mime')->nullable();
            $table->bigInteger('cv_size_bytes')->nullable();
            $table->char('cv_sha256', 64)->nullable();
            $table->string('status')->default(ApplicationStatus::PENDING->value)->index();
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('job_postings')->cascadeOnDelete();
            $table->foreign('applicant_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['job_id', 'applicant_user_id']);
        });
    }
}
