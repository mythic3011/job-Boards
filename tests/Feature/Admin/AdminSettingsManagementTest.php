<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\AdminSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Livewire\Volt\Volt;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class AdminSettingsManagementTest extends TestCase
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

        DB::table('roles')->insert([
            ['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('permissions')->insert([
            ['name' => 'admin.settings.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin.settings.update', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $permissionIds = DB::table('permissions')->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insert([
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_settings_page_renders_when_app_url_was_not_set_during_install(): void
    {
        Setting::set('app_name', 'Jobs Board');
        Setting::set('timezone', 'Asia/Hong_Kong');

        $response = $this->withBrowser()
            ->actingAs($this->adminUser())
            ->get(route('admin.settings.index'));

        $response->assertOk()
            ->assertSee('System Identity')
            ->assertSee('Application URL');
    }

    public function test_admin_can_fill_missing_system_config_from_settings_page(): void
    {
        Setting::set('demo_mode', 'false');
        Setting::set('registrations_open', 'true');
        Setting::set('maintenance_mode', 'false');
        Setting::set('app_name', 'Jobs Board');
        Setting::set('timezone', 'Asia/Hong_Kong');

        $admin = $this->adminUser();

        Volt::actingAs($admin)->test('admin.settings.index')
            ->assertSet('app_url', '')
            ->set('app_name', 'Jobs Boards')
            ->set('app_url', 'https://jb.mythic3011.com')
            ->set('timezone', 'UTC')
            ->call('save')
            ->assertSet('showConfirmModal', true)
            ->set('password', 'Password123!')
            ->call('confirmSave')
            ->assertSet('showConfirmModal', false)
            ->assertHasNoErrors();

        $this->assertSame('Jobs Boards', Setting::get('app_name'));
        $this->assertSame('https://jb.mythic3011.com', Setting::get('app_url'));
        $this->assertSame('UTC', Setting::get('timezone'));
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'settings.updated',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_admin_toggle_changes_are_treated_as_pending_changes_before_confirmation(): void
    {
        Setting::set('demo_mode', 'false');
        Setting::set('registrations_open', 'true');
        Setting::set('maintenance_mode', 'false');
        Setting::set('app_name', 'Jobs Board');
        Setting::set('app_url', 'https://jb.mythic3011.com');
        Setting::set('timezone', 'Asia/Hong_Kong');

        $admin = $this->adminUser();

        Volt::actingAs($admin)->test('admin.settings.index')
            ->assertSet('demo_mode', false)
            ->assertSet('current_demo_mode', false)
            ->set('demo_mode', true)
            ->assertSet('demo_mode', true)
            ->assertSet('current_demo_mode', false)
            ->call('save')
            ->assertSet('showConfirmModal', true)
            ->assertHasNoErrors();
    }

    public function test_admin_settings_service_persists_changes_clears_demo_data_and_dashboard_cache(): void
    {
        Setting::set('demo_mode', 'true');
        Setting::set('registrations_open', 'true');
        Setting::set('maintenance_mode', 'false');
        Setting::set('app_name', 'Jobs Board');
        Setting::set('app_url', 'https://jb.mythic3011.com');
        Setting::set('timezone', 'Asia/Hong_Kong');
        Setting::set('demo_seeded_at', now()->toIso8601String());

        $admin = $this->adminUser();
        $demoUser = User::factory()->create([
            'user_type' => 'individual',
            'password' => Hash::make('Password123!'),
        ]);
        Setting::set('demo_seed_user_ids', json_encode([$demoUser->id]));

        Cache::put('dashboard.stats', ['stale' => true], 300);

        $request = Request::create('/admin/settings', 'PUT', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);
        $request->setUserResolver(fn (): User => $admin);

        $result = app(AdminSettingsService::class)->updateSettings([
            'app_name' => 'Jobs Boards',
            'app_url' => 'https://jb.mythic3011.com',
            'timezone' => 'UTC',
            'demo_mode' => false,
            'registrations_open' => false,
            'maintenance_mode' => true,
        ], $request);

        $this->assertTrue($result['changed']);
        $this->assertTrue($result['demo_data_removed']);
        $this->assertSame('Jobs Boards', Setting::get('app_name'));
        $this->assertSame('UTC', Setting::get('timezone'));
        $this->assertFalse(Setting::getBool('demo_mode', true));
        $this->assertFalse(Setting::getBool('registrations_open', true));
        $this->assertTrue(Setting::getBool('maintenance_mode', false));
        $this->assertNull(Setting::get('demo_seeded_at'));
        $this->assertNull(Setting::get('demo_seed_user_ids'));
        $this->assertDatabaseMissing('users', ['id' => $demoUser->id]);
        $this->assertNull(Cache::get('dashboard.stats'));
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'settings.updated',
            'actor_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'demo.data_cleared',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_confirm_save_requires_password_and_denied_attempt_has_no_side_effects(): void
    {
        Setting::set('demo_mode', 'false');
        Setting::set('registrations_open', 'true');
        Setting::set('maintenance_mode', 'false');
        Setting::set('app_name', 'Jobs Board');
        Setting::set('app_url', 'https://jb.mythic3011.com');
        Setting::set('timezone', 'Asia/Hong_Kong');

        $admin = $this->adminUser();

        Volt::actingAs($admin)->test('admin.settings.index')
            ->set('app_name', 'Jobs Board Updated')
            ->call('save')
            ->assertSet('showConfirmModal', true)
            ->call('confirmSave')
            ->assertHasErrors(['password'])
            ->assertSet('showConfirmModal', true);

        $this->assertSame('Jobs Board', Setting::get('app_name'));
        $this->assertDatabaseMissing('audit_logs', [
            'event_type' => 'settings.updated',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_confirm_save_with_wrong_password_has_no_side_effects(): void
    {
        Setting::set('demo_mode', 'false');
        Setting::set('registrations_open', 'true');
        Setting::set('maintenance_mode', 'false');
        Setting::set('app_name', 'Jobs Board');
        Setting::set('app_url', 'https://jb.mythic3011.com');
        Setting::set('timezone', 'Asia/Hong_Kong');

        $admin = $this->adminUser();

        Volt::actingAs($admin)->test('admin.settings.index')
            ->set('app_name', 'Jobs Board Updated')
            ->call('save')
            ->assertSet('showConfirmModal', true)
            ->set('password', 'WrongPassword123!')
            ->call('confirmSave')
            ->assertHasErrors(['password'])
            ->assertSet('showConfirmModal', true);

        $this->assertSame('Jobs Board', Setting::get('app_name'));
        $this->assertDatabaseMissing('audit_logs', [
            'event_type' => 'settings.updated',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_confirm_save_rate_limited_attempt_has_no_side_effects_and_no_success_audit(): void
    {
        Setting::set('demo_mode', 'false');
        Setting::set('registrations_open', 'true');
        Setting::set('maintenance_mode', 'false');
        Setting::set('app_name', 'Jobs Board');
        Setting::set('app_url', 'https://jb.mythic3011.com');
        Setting::set('timezone', 'Asia/Hong_Kong');

        $admin = $this->adminUser();
        $rateLimitKey = 'settings-update:'.$admin->id;

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($rateLimitKey, 60);
        }

        Volt::actingAs($admin)->test('admin.settings.index')
            ->set('app_name', 'Jobs Board Updated')
            ->call('save')
            ->assertSet('showConfirmModal', true)
            ->set('password', 'Password123!')
            ->call('confirmSave')
            ->assertSet('showConfirmModal', false);

        $this->assertSame('Jobs Board', Setting::get('app_name'));
        $this->assertDatabaseMissing('audit_logs', [
            'event_type' => 'settings.updated',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_successful_confirm_save_audit_contains_minimal_change_metadata(): void
    {
        Setting::set('demo_mode', 'false');
        Setting::set('registrations_open', 'true');
        Setting::set('maintenance_mode', 'false');
        Setting::set('app_name', 'Jobs Board');
        Setting::set('app_url', 'https://jb.mythic3011.com');
        Setting::set('timezone', 'Asia/Hong_Kong');

        $admin = $this->adminUser();

        Volt::actingAs($admin)->test('admin.settings.index')
            ->set('app_name', 'Jobs Boards')
            ->set('app_url', 'https://jobs.example.test')
            ->set('timezone', 'UTC')
            ->call('save')
            ->set('password', 'Password123!')
            ->call('confirmSave')
            ->assertHasNoErrors();

        $audit = \App\Models\AuditLog::query()
            ->where('event_type', 'settings.updated')
            ->where('actor_user_id', $admin->id)
            ->latest('occurred_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('setting', $audit->target_type);
        $this->assertSame('Jobs Board', $audit->meta['app_name']['before'] ?? null);
        $this->assertSame('Jobs Boards', $audit->meta['app_name']['after'] ?? null);
        $this->assertSame('Asia/Hong_Kong', $audit->meta['timezone']['before'] ?? null);
        $this->assertSame('UTC', $audit->meta['timezone']['after'] ?? null);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create([
            'user_type' => 'admin',
            'password' => Hash::make('Password123!'),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);
        $user->assignRole('admin');
        $user->givePermissionTo('admin.settings.view');
        $user->givePermissionTo('admin.settings.update');

        return $user;
    }
}
