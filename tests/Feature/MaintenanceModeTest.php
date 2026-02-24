<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Setting::markSetupCompleted();
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['user_type' => 'individual']);
        $user->assignRole('admin');
        return $user;
    }

    private function regularUser(): User
    {
        return User::factory()->create(['user_type' => 'individual']);
    }

    // User-Agent header required to pass HandleSuspiciousUserAgent middleware in tests
    private function withBrowser(): static
    {
        return $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; TestBrowser/1.0)');
    }

    private function enableMaintenance(): void
    {
        Setting::setBool('maintenance_mode', true);
    }

    // --- Maintenance OFF (normal operation) ---

    public function test_job_routes_accessible_when_maintenance_off(): void
    {
        $this->withBrowser()
             ->get(route('jobs.index'))
             ->assertOk();
    }

    public function test_profile_routes_accessible_when_maintenance_off(): void
    {
        $user = $this->regularUser();
        $this->withBrowser()
             ->actingAs($user)
             ->get(route('profile.show'))
             ->assertOk();
    }

    public function test_registration_accessible_when_maintenance_off(): void
    {
        $this->withBrowser()
             ->get(route('register'))
             ->assertOk();
    }

    public function test_home_accessible_when_maintenance_off(): void
    {
        $this->withBrowser()
             ->get(route('home'))
             ->assertOk();
    }

    // --- Maintenance ON: guests ---

    public function test_guest_sees_503_on_job_routes_during_maintenance(): void
    {
        $this->enableMaintenance();

        $this->withBrowser()
             ->get(route('jobs.index'))
             ->assertStatus(503);
    }

    public function test_guest_sees_503_on_home_during_maintenance(): void
    {
        $this->enableMaintenance();

        $this->withBrowser()
             ->get(route('home'))
             ->assertStatus(503);
    }

    public function test_guest_sees_404_on_profile_routes_during_maintenance(): void
    {
        $this->enableMaintenance();

        // auth middleware fires before maintenance.check for profile routes,
        // so unauthenticated guests see 404 (protected route hidden) rather than 503.
        $this->withBrowser()
             ->get(route('profile.show'))
             ->assertNotFound();
    }

    public function test_guest_cannot_register_during_maintenance(): void
    {
        $this->enableMaintenance();

        $this->withBrowser()
             ->post(route('register.store'), [
                 'name'                  => 'Test User',
                 'email'                 => 'test@example.com',
                 'login_id'              => 'testuser',
                 'password'              => 'password',
                 'password_confirmation' => 'password',
                 'user_type'             => 'individual',
             ])
             ->assertStatus(503);
    }

    public function test_guest_cannot_request_password_reset_during_maintenance(): void
    {
        $this->enableMaintenance();

        $this->withBrowser()
             ->post(route('password.email'), ['email' => 'test@example.com'])
             ->assertStatus(503);
    }

    // --- Maintenance ON: authenticated non-admin ---

    public function test_authenticated_non_admin_can_access_jobs_during_maintenance(): void
    {
        $this->enableMaintenance();

        $user = $this->regularUser();
        $this->withBrowser()
             ->actingAs($user)
             ->get(route('jobs.index'))
             ->assertOk();
    }

    public function test_authenticated_non_admin_can_access_profile_during_maintenance(): void
    {
        $this->enableMaintenance();

        $user = $this->regularUser();
        $this->withBrowser()
             ->actingAs($user)
             ->get(route('profile.show'))
             ->assertOk();
    }

    // --- Maintenance ON: admin bypass ---

    public function test_admin_can_access_job_routes_during_maintenance(): void
    {
        $this->enableMaintenance();

        $admin = $this->adminUser();
        $this->withBrowser()
             ->actingAs($admin)
             ->get(route('jobs.index'))
             ->assertOk();
    }

    public function test_admin_bypass_is_recorded_in_audit_logs(): void
    {
        $this->enableMaintenance();

        $admin = $this->adminUser();
        $this->withBrowser()
             ->actingAs($admin)
             ->get(route('jobs.index'))
             ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event_type'    => 'maintenance.admin_bypass',
            'actor_user_id' => $admin->id,
        ]);
    }
}
