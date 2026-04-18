<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
