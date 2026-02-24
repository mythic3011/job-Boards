<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['user_type' => 'individual']);
        $user->givePermissionTo('admin.system.view');
        return $user;
    }

    private function withBrowser(): static
    {
        return $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; TestBrowser/1.0)');
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
             ->assertForbidden();
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
}
