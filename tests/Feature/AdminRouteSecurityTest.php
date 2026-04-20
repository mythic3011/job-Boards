<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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

    public function test_pending_non_admin_requests_are_hidden(): void
    {
        $user = User::factory()->individual()->create([
            'registration_state' => 'pending_2fa',
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['CODE-ONE'])),
        ]);

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

    public function test_admin_without_required_permission_creates_canonical_permission_deny_audit_log(): void
    {
        $admin = User::factory()->create([
            'user_type' => 'admin',
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->attachAdminRole($admin);
        $this->createPermission('admin.system.view');

        $this->withBrowser()
            ->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();

        $log = AuditLog::query()
            ->where('event_type', 'audit.admin.permission.denied')
            ->latest('occurred_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('laravel', $log->source);
        $this->assertSame('denied', $log->outcome);
        $this->assertSame(403, $log->status_code);
        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame('admin_route', $log->target_type);
        $this->assertSame('admin.audit-logs.index', $log->target_idcode);
        $this->assertSame('admin.system.view', $log->meta['policy'] ?? null);
    }

    public function test_legacy_admin_identity_with_direct_permission_can_access_admin_routes(): void
    {
        $admin = User::factory()->create([
            'user_type' => 'admin',
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->createPermission('admin.system.view');
        $this->assignDirectPermission($admin, 'admin.system.view');

        $this->withBrowser()
            ->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk();
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

    private function createPermission(string $name): void
    {
        DB::table('permissions')->insert([
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function assignDirectPermission(User $user, string $permission): void
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
