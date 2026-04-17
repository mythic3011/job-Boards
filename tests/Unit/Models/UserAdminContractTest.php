<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class UserAdminContractTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createPermissionTables();
    }

    public function test_role_based_admin_users_still_resolve_as_admin_when_permission_tables_are_available(): void
    {
        $user = User::factory()->company()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_non_legacy_users_fail_closed_when_admin_role_tables_are_unavailable(): void
    {
        $user = User::factory()->company()->create();

        Schema::drop('model_has_roles');
        Schema::drop('roles');

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse($user->fresh()->isAdmin());
    }
}
