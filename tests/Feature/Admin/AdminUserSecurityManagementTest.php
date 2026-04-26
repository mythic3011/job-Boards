<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AdminUserSecurityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Mockery;
use Mockery\MockInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class AdminUserSecurityManagementTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists('App\\Http\\Middleware\\HandleSuspiciousUserAgent')) {
            eval('namespace App\\Http\\Middleware; class HandleSuspiciousUserAgent { public function isSuspicious(\\Illuminate\\Http\\Request $request): bool { return false; } }');
        }

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createAuditLogsTable();
        $this->createPermissionTables();
        $this->createPasswordResetTokensTable();

        DB::table('roles')->insert([
            ['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('permissions')->insert([
            ['name' => 'admin.users.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin.users.force_password_reset', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin.users.lock', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin.users.unlock', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin.users.delete', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $permissionIds = DB::table('permissions')->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insert([
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_component_delegates_password_reset_to_admin_user_security_service(): void
    {
        $admin = $this->makeAdmin();
        $target = $this->makeUser();

        $this->mock(AdminUserSecurityService::class, function (MockInterface $mock) use ($admin, $target): void {
            $mock->shouldReceive('forcePasswordReset')
                ->once()
                ->withArgs(fn (User $actor, string $targetUserId): bool => $actor->is($admin) && $targetUserId === $target->id)
                ->andReturn([
                    'reset_url' => 'https://example.test/reset-password/mock-token?email='.$target->email,
                    'reset_user_name' => $target->nickname,
                ]);
        });

        Volt::actingAs($admin)->test('admin.users.index')
            ->call('forcePasswordReset', $target->id)
            ->assertSet('resetUrl', 'https://example.test/reset-password/mock-token?email='.$target->email)
            ->assertSet('resetUserName', $target->nickname);
    }

    public function test_admin_can_issue_password_reset_link_via_component(): void
    {
        $admin = $this->makeAdmin();
        $target = $this->makeUser();

        $component = Volt::actingAs($admin)->test('admin.users.index')
            ->call('forcePasswordReset', $target->id)
            ->assertHasNoErrors();

        $resetUrl = $component->get('resetUrl');

        $this->assertIsString($resetUrl);
        $this->assertStringContainsString('/reset-password/', $resetUrl);
        $this->assertStringContainsString('email='.urlencode($target->email), $resetUrl);
        $this->assertSame($target->nickname, $component->get('resetUserName'));
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'user.force_password_reset',
            'target_idcode' => $target->idcode,
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_lock_and_unlock_user_via_component(): void
    {
        $admin = $this->makeAdmin();
        $target = $this->makeUser();

        Volt::actingAs($admin)->test('admin.users.index')
            ->call('lockUser', $target->id)
            ->assertHasNoErrors();

        $this->assertTrue($target->fresh()->isLocked());
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'user.locked',
            'target_idcode' => $target->idcode,
        ]);

        Volt::actingAs($admin)->test('admin.users.index')
            ->call('unlockUser', $target->id)
            ->assertHasNoErrors();

        $this->assertFalse($target->fresh()->isLocked());
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'user.unlocked',
            'target_idcode' => $target->idcode,
        ]);
    }

    public function test_component_blocks_self_delete_with_validation_error(): void
    {
        $admin = $this->makeAdmin();

        Volt::actingAs($admin)->test('admin.users.index')
            ->set('confirmingUserDeletion', $admin->id)
            ->call('deleteUser')
            ->assertHasErrors(['delete']);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_user_delete_requires_delete_permission(): void
    {
        $admin = $this->makeAdmin([
            'admin.users.view',
            'admin.users.force_password_reset',
            'admin.users.lock',
            'admin.users.unlock',
        ]);
        $target = $this->makeUser();

        Volt::actingAs($admin)->test('admin.users.index')
            ->set('confirmingUserDeletion', $target->id)
            ->call('deleteUser')
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    /**
     * @param  list<string>|null  $permissions
     */
    private function makeAdmin(?array $permissions = null): User
    {
        $admin = User::factory()->create([
            'user_type' => 'admin',
            'password' => Hash::make('Password123!'),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);

        $admin->givePermissionTo($permissions ?? [
            'admin.users.view',
            'admin.users.force_password_reset',
            'admin.users.lock',
            'admin.users.unlock',
            'admin.users.delete',
        ]);

        return $admin;
    }

    private function makeUser(): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_'.Str::uuid(),
            'nickname' => 'Target User',
            'login_id' => 'target_'.Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(6)).'@example.com',
            'password' => Hash::make('Password123!'),
            'user_type' => 'individual',
        ]);
    }

    private function createPasswordResetTokensTable(): void
    {
        \Illuminate\Support\Facades\Schema::create('password_reset_tokens', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
}
