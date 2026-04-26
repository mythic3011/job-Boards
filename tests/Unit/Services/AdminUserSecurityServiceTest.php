<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AdminUserSecurityService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class AdminUserSecurityServiceTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createAuditLogsTable();
        $this->createPermissionTables();
        $this->createPasswordResetTokensTable();

        DB::table('roles')->insert([
            'name' => 'admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('permissions')->insert([
            ['name' => 'admin.users.delete', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin.users.lock', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin.users.unlock', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin.users.force_password_reset', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $roleId = DB::table('roles')->where('name', 'admin')->value('id');
        $permissionIds = DB::table('permissions')->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_force_password_reset_marks_admin_reset_cache_and_logs_audit_event(): void
    {
        $admin = $this->adminUser();
        $target = User::factory()->individual()->create();

        Cache::flush();

        $result = app(AdminUserSecurityService::class)->forcePasswordReset($admin, $target->id);

        $token = basename((string) parse_url($result['reset_url'], PHP_URL_PATH));
        parse_str((string) parse_url($result['reset_url'], PHP_URL_QUERY), $query);

        $this->assertSame($target->nickname, $result['reset_user_name']);
        $this->assertSame($target->email, $query['email'] ?? null);
        $this->assertTrue(Cache::has('admin_reset:'.$token));
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'user.force_password_reset',
            'actor_user_id' => $admin->id,
            'target_idcode' => $target->idcode,
        ]);
    }

    public function test_delete_user_denies_self_target_even_for_admin_with_delete_permission(): void
    {
        $admin = $this->adminUser();

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('You cannot delete your own account.');

        app(AdminUserSecurityService::class)->deleteUser($admin, $admin->id);
    }

    public function test_lock_user_updates_target_and_clears_dashboard_cache(): void
    {
        $admin = $this->adminUser();
        $target = User::factory()->individual()->create([
            'locked_until' => null,
        ]);

        Cache::put('dashboard.stats', ['warm' => true], 300);

        app(AdminUserSecurityService::class)->lockUser($admin, $target->id);

        $this->assertNotNull($target->fresh()->locked_until);
        $this->assertFalse(Cache::has('dashboard.stats'));
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'user.locked',
            'actor_user_id' => $admin->id,
            'target_idcode' => $target->idcode,
        ]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create([
            'user_type' => 'admin',
        ]);

        $user->assignRole('admin');

        return $user;
    }

    private function createPasswordResetTokensTable(): void
    {
        Schema::create('password_reset_tokens', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
}
