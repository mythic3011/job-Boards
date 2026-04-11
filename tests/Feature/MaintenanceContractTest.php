<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class MaintenanceContractTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        $this->useInMemorySqlite();
        $this->createSettingsTable();
        $this->createUsersTable();
        $this->createPermissionTables();
        $this->createAuditLogsTable();
        $this->markSetupCompleted();
        $this->enableMaintenance();
    }

    public function test_guest_sees_503_on_register_page_during_maintenance(): void
    {
        $this->withBrowser()
            ->get(route('register'))
            ->assertStatus(503);
    }

    public function test_guest_cannot_register_during_maintenance(): void
    {
        $this->withBrowser()
            ->post(route('register.store'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'login_id' => 'testuser',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'user_type' => 'individual',
            ])
            ->assertStatus(503);
    }

    public function test_guest_sees_503_on_forgot_password_page_during_maintenance(): void
    {
        $this->withBrowser()
            ->get(route('password.request'))
            ->assertStatus(503);
    }

    public function test_guest_cannot_request_password_reset_during_maintenance(): void
    {
        $this->withBrowser()
            ->post(route('password.email'), ['email' => 'test@example.com'])
            ->assertStatus(503);
    }

    public function test_guest_sees_503_on_reset_password_page_during_maintenance(): void
    {
        $this->withBrowser()
            ->get(route('password.reset', ['token' => 'test-token']))
            ->assertStatus(503);
    }

    public function test_guest_cannot_submit_password_reset_during_maintenance(): void
    {
        $this->withBrowser()
            ->post(route('password.update'), [
                'token' => 'test-token',
                'email' => 'test@example.com',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
            ])
            ->assertStatus(503);
    }

    public function test_guest_can_still_access_login_page_during_maintenance(): void
    {
        $this->withBrowser()
            ->get(route('login'))
            ->assertOk();
    }

    public function test_non_admin_login_is_denied_during_maintenance(): void
    {
        $user = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'member1',
            'email' => 'member@example.com',
        ]);

        $this->withBrowser()
            ->post(route('login.store'), [
                'login_id' => $user->login_id,
                'password' => 'StrongPass123!',
            ])
            ->assertStatus(503);

        $this->assertGuest();
    }

    public function test_admin_login_is_allowed_during_maintenance(): void
    {
        $admin = $this->createUser([
            'user_type' => 'admin',
            'login_id' => 'admin1',
            'email' => 'admin@example.com',
        ]);
        $this->attachAdminRole($admin);

        $this->withBrowser()
            ->post(route('login.store'), [
                'login_id' => $admin->login_id,
                'password' => 'StrongPass123!',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_non_admin_two_factor_challenge_is_blocked_during_maintenance(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $member = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'member2fa',
            'email' => 'member2fa@example.com',
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->withBrowser()
            ->withSession(['login.id' => $member->id, 'login.remember' => false])
            ->get(route('two-factor.login'))
            ->assertStatus(503);

        $validCode = app(Google2FA::class)->getCurrentOtp($secret);

        $this->withBrowser()
            ->withSession(['login.id' => $member->id, 'login.remember' => false])
            ->post(route('two-factor.login.store'), ['code' => $validCode])
            ->assertStatus(503);

        $this->assertGuest();
    }

    public function test_admin_two_factor_challenge_is_allowed_during_maintenance(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $admin = $this->createUser([
            'user_type' => 'admin',
            'login_id' => 'admin2fa',
            'email' => 'admin2fa@example.com',
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->attachAdminRole($admin);

        $this->withBrowser()
            ->withSession(['login.id' => $admin->id, 'login.remember' => false])
            ->get(route('two-factor.login'))
            ->assertOk();

        $validCode = app(Google2FA::class)->getCurrentOtp($secret);

        $this->withBrowser()
            ->withSession(['login.id' => $admin->id, 'login.remember' => false])
            ->post(route('two-factor.login.store'), ['code' => $validCode])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($admin);
    }

    private function markSetupCompleted(): void
    {
        Setting::setBool('setup_completed', true);
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
            'name' => 'admin.system.view',
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
