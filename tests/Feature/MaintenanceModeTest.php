<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMaintenanceMode;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class MaintenanceModeTest extends TestCase
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

        Route::middleware(CheckMaintenanceMode::class)
            ->get('/_test/maintenance/public', fn () => response('public-ok'))
            ->name('test.maintenance.public');

        Route::middleware(['auth', CheckMaintenanceMode::class])
            ->get('/_test/maintenance/protected', fn () => response('protected-ok'))
            ->name('test.maintenance.protected');
    }

    public function test_public_route_is_accessible_when_maintenance_is_off(): void
    {
        $this->withBrowser()
            ->get('/_test/maintenance/public')
            ->assertOk()
            ->assertSee('public-ok');
    }

    public function test_guest_sees_503_on_public_route_during_maintenance(): void
    {
        $this->enableMaintenance();

        $this->withBrowser()
            ->get('/_test/maintenance/public')
            ->assertStatus(503);
    }

    public function test_guest_sees_404_on_protected_route_during_maintenance(): void
    {
        $this->enableMaintenance();

        $this->withBrowser()
            ->get('/_test/maintenance/protected')
            ->assertNotFound();
    }

    public function test_authenticated_non_admin_is_blocked_during_maintenance(): void
    {
        $this->enableMaintenance();
        $user = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'member1',
            'email' => 'member@example.com',
        ]);

        $this->withBrowser()
            ->actingAs($user)
            ->get('/_test/maintenance/public')
            ->assertStatus(503);
    }

    public function test_admin_can_access_routes_during_maintenance(): void
    {
        $this->enableMaintenance();
        $admin = $this->createUser([
            'user_type' => 'admin',
            'login_id' => 'admin1',
            'email' => 'admin@example.com',
        ]);
        $this->attachAdminRole($admin);

        $this->withBrowser()
            ->actingAs($admin)
            ->get('/_test/maintenance/public')
            ->assertOk()
            ->assertSee('public-ok');

        $this->withBrowser()
            ->actingAs($admin)
            ->get('/_test/maintenance/protected')
            ->assertOk()
            ->assertSee('protected-ok');
    }

    public function test_admin_bypass_is_recorded_in_audit_logs(): void
    {
        $this->enableMaintenance();
        $admin = $this->createUser([
            'user_type' => 'admin',
            'login_id' => 'admin2',
            'email' => 'admin2@example.com',
        ]);
        $this->attachAdminRole($admin);

        $this->withBrowser()
            ->actingAs($admin)
            ->get('/_test/maintenance/public')
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'maintenance.admin_bypass',
            'actor_user_id' => $admin->id,
        ]);
    }

    private function enableMaintenance(): void
    {
        Setting::setBool('maintenance_mode', true);
    }

    private function createUser(array $attributes): User
    {
        return User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'idcode' => 'user_' . \Illuminate\Support\Str::uuid(),
            'nickname' => 'Test User',
            'password' => Hash::make('StrongPass123!'),
            ...$attributes,
        ]);
    }

    private function attachAdminRole(User $user): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'maintenance.bypass',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);

        DB::table('role_has_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
