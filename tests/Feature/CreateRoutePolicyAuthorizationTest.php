<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class CreateRoutePolicyAuthorizationTest extends TestCase
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

        DB::table('settings')->insert([
            'key' => 'setup_completed',
            'value' => 'true',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unauthorized_user_cannot_open_or_post_job_creation_routes(): void
    {
        $individual = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'individual_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);

        $this->actingAs($individual)
            ->withBrowser()
            ->get(route('jobs.create'))
            ->assertForbidden();

        $this->actingAs($individual)
            ->withBrowser()
            ->post(route('jobs.store'), [
                'title' => 'Blocked Job',
                'requirement' => 'N/A',
                'duty' => 'N/A',
            ])
            ->assertForbidden();
    }

    public function test_authorized_company_can_open_and_post_job_creation_routes(): void
    {
        $company = $this->createUser([
            'user_type' => 'company',
            'login_id' => 'company_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);
        $this->grantPermission($company, 'create jobs');

        $this->actingAs($company)
            ->withBrowser()
            ->get(route('jobs.create'))
            ->assertOk();

        $this->actingAs($company)
            ->withBrowser()
            ->post(route('jobs.store'), [])
            ->assertSessionHasErrors(['title', 'requirement', 'duty']);
    }

    public function test_unauthorized_user_cannot_open_or_post_application_creation_routes(): void
    {
        $company = $this->createUser([
            'user_type' => 'company',
            'login_id' => 'company_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);
        $job = $this->createJobPosting();

        $this->actingAs($company)
            ->withBrowser()
            ->get(route('applications.create', $job->idcode))
            ->assertForbidden();

        $this->actingAs($company)
            ->withBrowser()
            ->post(route('applications.store', $job->idcode), [])
            ->assertForbidden();
    }

    public function test_authorized_individual_can_open_and_post_application_creation_routes(): void
    {
        $individual = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'individual_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);
        $this->grantPermission($individual, 'apply to jobs');

        $job = $this->createJobPosting();

        $this->actingAs($individual)
            ->withBrowser()
            ->get(route('applications.create', $job->idcode))
            ->assertOk();

        $this->actingAs($individual)
            ->withBrowser()
            ->post(route('applications.store', $job->idcode), [])
            ->assertSessionHasErrors(['cv_file']);
    }

    public function test_create_routes_are_guarded_by_policy_middleware(): void
    {
        $this->assertContains(
            'can:create,' . \App\Models\JobPosting::class,
            app('router')->getRoutes()->getByName('jobs.create')->gatherMiddleware()
        );
        $this->assertContains(
            'can:create,' . \App\Models\JobPosting::class,
            app('router')->getRoutes()->getByName('jobs.store')->gatherMiddleware()
        );
        $this->assertContains(
            'can:create,' . \App\Models\Application::class,
            app('router')->getRoutes()->getByName('applications.create')->gatherMiddleware()
        );
        $this->assertContains(
            'can:create,' . \App\Models\Application::class,
            app('router')->getRoutes()->getByName('applications.store')->gatherMiddleware()
        );
    }

    private function createJobPosting(): JobPosting
    {
        $company = $this->createUser([
            'user_type' => 'company',
            'login_id' => 'job_owner_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
        ]);

        return JobPosting::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'job_' . Str::uuid(),
            'company_user_id' => $company->id,
            'title' => 'Platform Engineer',
            'requirement' => 'Build secure features',
            'duty' => 'Review applications',
        ]);
    }

    private function createUser(array $attributes): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_' . Str::uuid(),
            'nickname' => 'Route Test User',
            'password' => Hash::make('StrongPass123!'),
            ...$attributes,
        ]);
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
}
