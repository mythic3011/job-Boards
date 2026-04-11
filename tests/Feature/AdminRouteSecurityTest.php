<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class AdminRouteSecurityTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createSettingsTable();
        $this->createAuditLogsTable();
        $this->createPermissionTables();
    }

    public function test_guest_admin_requests_are_hidden(): void
    {
        $this->withBrowser()
            ->get(route('admin.audit-logs.index'))
            ->assertNotFound();
    }

    public function test_non_admin_requests_are_hidden(): void
    {
        $user = User::factory()->individual()->create();

        $this->withBrowser()
            ->actingAs($user)
            ->get(route('admin.audit-logs.index'))
            ->assertNotFound();
    }

    public function test_admin_without_confirmed_two_factor_is_redirected_to_setup(): void
    {
        $admin = User::factory()->create([
            'user_type' => 'admin',
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
        $this->attachAdminRole($admin);

        $this->withBrowser()
            ->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertRedirect(route('profile.two-factor'));
    }

    private function attachAdminRole(User $user): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
