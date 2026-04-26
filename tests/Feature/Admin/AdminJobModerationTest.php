<?php

namespace Tests\Feature\Admin;

use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class AdminJobModerationTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createAuditLogsTable();
        $this->createPermissionTables();
        $this->createJobPostingsTable();
        $this->createApplicationsTable();

        DB::table('roles')->insert([
            ['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('permissions')->insert([
            ['name' => 'admin.jobs.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin.jobs.moderate', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $permissionIds = DB::table('permissions')->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insert([
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_delete_job_through_moderation_service_path(): void
    {
        [$admin, $job] = $this->makeAdminAndJobFixture();

        Volt::actingAs($admin)->test('admin.jobs.index')
            ->call('deleteJob', $job->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('job_postings', ['id' => $job->id]);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'admin.job.deleted',
            'target_idcode' => $job->idcode,
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_non_moderator_cannot_delete_job(): void
    {
        [$admin, $job] = $this->makeAdminAndJobFixture(['admin.jobs.view']);

        Volt::actingAs($admin)->test('admin.jobs.index')
            ->call('deleteJob', $job->id)
            ->assertForbidden();

        $this->assertDatabaseHas('job_postings', ['id' => $job->id]);
    }

    public function test_non_admin_actor_with_moderate_permission_cannot_delete_job(): void
    {
        $actor = User::factory()->create([
            'user_type' => 'company',
            'password' => Hash::make('Password123!'),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);
        $actor->givePermissionTo(['admin.jobs.view', 'admin.jobs.moderate']);

        $jobOwner = User::factory()->create([
            'user_type' => 'company',
        ]);

        $job = JobPosting::factory()->for($jobOwner, 'companyUser')->create();

        Volt::actingAs($actor)->test('admin.jobs.index')
            ->call('deleteJob', $job->id)
            ->assertForbidden();

        $this->assertDatabaseHas('job_postings', ['id' => $job->id]);
    }

    /**
     * @param  list<string>|null  $permissions
     * @return array{0: User, 1: JobPosting}
     */
    private function makeAdminAndJobFixture(?array $permissions = null): array
    {
        $admin = User::factory()->create([
            'user_type' => 'admin',
            'password' => Hash::make('Password123!'),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);
        $admin->givePermissionTo($permissions ?? ['admin.jobs.view', 'admin.jobs.moderate']);

        $company = User::factory()->create([
            'user_type' => 'company',
        ]);

        $job = JobPosting::factory()->for($company, 'companyUser')->create();

        return [$admin, $job];
    }
}
