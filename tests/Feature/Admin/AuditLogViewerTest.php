<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create(['user_type' => 'individual']);
        $user->givePermissionTo('admin.system.view');
        return $user;
    }

    public function test_guest_cannot_access_audit_logs(): void
    {
        $this->get(route('admin.audit-logs.index'))
             ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_audit_logs(): void
    {
        $user = User::factory()->create(['user_type' => 'individual']);
        $this->actingAs($user)
             ->get(route('admin.audit-logs.index'))
             ->assertForbidden();
    }

    public function test_admin_can_view_audit_logs(): void
    {
        $admin = $this->adminUser();
        AuditLog::factory()->count(3)->create();

        $this->actingAs($admin)
             ->get(route('admin.audit-logs.index'))
             ->assertOk()
             ->assertSee('Audit Logs');
    }

    public function test_viewing_audit_logs_creates_audit_log_entry(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
             ->get(route('admin.audit-logs.index'));

        $this->assertDatabaseHas('audit_logs', [
            'event_type'    => 'audit_log.viewed',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_filter_by_event_type(): void
    {
        $admin = $this->adminUser();
        AuditLog::factory()->create(['event_type' => 'login_failed', 'occurred_at' => now()]);
        AuditLog::factory()->create(['event_type' => 'user_registered', 'occurred_at' => now()]);

        Volt::test('admin.audit-logs')
            ->actingAs($admin)
            ->set('eventType', 'login_failed')
            ->set('dateRange', 'all')
            ->assertSee('login_failed')
            ->assertDontSee('user_registered');
    }

    public function test_filter_by_status_failed(): void
    {
        $admin = $this->adminUser();
        AuditLog::factory()->create(['event_type' => 'login_failed', 'status_code' => 401, 'occurred_at' => now()]);
        AuditLog::factory()->create(['event_type' => 'user_registered', 'status_code' => 200, 'occurred_at' => now()]);

        Volt::test('admin.audit-logs')
            ->actingAs($admin)
            ->set('status', 'failed')
            ->set('dateRange', 'all')
            ->assertSee('login_failed')
            ->assertDontSee('user_registered');
    }
}
