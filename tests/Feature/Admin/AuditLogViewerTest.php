<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\TestCase;
use Tests\Concerns\UsesInMemorySqlite;

/**
 * Verification path: sqlite-safe.
 */
class AuditLogViewerTest extends TestCase
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
            ['name' => 'company', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'individual', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('permissions')->insert([
            ['name' => 'admin.system.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $adminPermissionId = DB::table('permissions')->where('name', 'admin.system.view')->value('id');
        DB::table('role_has_permissions')->insert([
            'role_id' => $adminRoleId,
            'permission_id' => $adminPermissionId,
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function adminUser(): User
    {
        $user = User::factory()->create([
            'user_type' => 'admin',
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);
        $user->assignRole('admin');
        $user->givePermissionTo('admin.system.view');

        return $user;
    }

    private function adminUserWithoutTwoFactor(): User
    {
        $user = $this->adminUser();
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $user;
    }

    public function test_guest_cannot_access_audit_logs(): void
    {
        $this->withBrowser()
             ->get(route('admin.audit-logs.index'))
             ->assertNotFound();
    }

    public function test_non_admin_cannot_access_audit_logs(): void
    {
        $user = User::factory()->create(['user_type' => 'individual']);
        $this->withBrowser()
             ->actingAs($user)
             ->get(route('admin.audit-logs.index'))
             ->assertNotFound();
    }

    public function test_admin_without_confirmed_two_factor_is_redirected_to_setup(): void
    {
        $admin = $this->adminUserWithoutTwoFactor();

        $this->withBrowser()
             ->actingAs($admin)
             ->get(route('admin.audit-logs.index'))
             ->assertRedirect(route('profile.two-factor'));
    }

    public function test_admin_can_view_audit_logs(): void
    {
        $admin = $this->adminUser();
        AuditLog::factory()->count(3)->create();

        $this->withBrowser()
             ->actingAs($admin)
             ->get(route('admin.audit-logs.index'))
             ->assertOk()
             ->assertSee('Audit Logs');
    }

    public function test_viewing_audit_logs_creates_audit_log_entry(): void
    {
        $admin = $this->adminUser();

        $this->withBrowser()
             ->actingAs($admin)
             ->get(route('admin.audit-logs.index'));

        $this->assertDatabaseHas('audit_logs', [
            'event_type'    => 'audit_log.viewed',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_filter_by_event_type(): void
    {
        $admin = $this->adminUser();
        AuditLog::factory()->create(['event_type' => 'login_failed', 'target_idcode' => 'target-aaa', 'occurred_at' => now()]);
        AuditLog::factory()->create(['event_type' => 'login_failed', 'target_idcode' => 'target-bbb', 'occurred_at' => now()]);
        AuditLog::factory()->create(['event_type' => 'user_registered', 'target_idcode' => 'target-ccc', 'occurred_at' => now()]);

        Volt::actingAs($admin)->test('admin.audit-logs')
            ->set('eventType', 'login_failed')
            ->set('dateRange', 'all')
            ->assertSee('target-aaa')
            ->assertDontSee('target-ccc');
    }

    public function test_filter_by_status_failed(): void
    {
        $admin = $this->adminUser();
        AuditLog::factory()->create(['event_type' => 'login_failed', 'status_code' => 401, 'target_idcode' => 'target-ddd', 'occurred_at' => now()]);
        AuditLog::factory()->create(['event_type' => 'user_registered', 'status_code' => 200, 'target_idcode' => 'target-eee', 'occurred_at' => now()]);

        Volt::actingAs($admin)->test('admin.audit-logs')
            ->set('status', 'failed')
            ->set('dateRange', 'all')
            ->assertSee('target-ddd')
            ->assertDontSee('target-eee');
    }

    public function test_admin_audit_log_viewer_shows_a_concise_bot_fingerprint_summary(): void
    {
        $admin = $this->adminUser();

        AuditLog::factory()->create([
            'event_type' => 'bot_fingerprint_probe',
            'meta' => [
                'probe' => 'banned_page',
                'signal' => 'page_load',
                'headless' => true,
            ],
            'occurred_at' => now(),
        ]);

        $this->withBrowser()
            ->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSeeText('Bot Fingerprint Probe')
            ->assertSeeText('Probe: banned_page | signal: page_load | headless: yes');
    }

    public function test_admin_audit_log_viewer_humanizes_honeypot_trigger_reason(): void
    {
        $admin = $this->adminUser();

        AuditLog::factory()->create([
            'event_type' => 'honeypot.triggered',
            'meta' => [
                'reason' => 'filled_honeypot_field',
                'field_name' => 'website',
                'field_filled' => true,
            ],
            'occurred_at' => now(),
        ]);

        $this->withBrowser()
            ->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSeeText('Honeypot Triggered')
            ->assertSeeText('Honeypot field filled: website');
    }
}
