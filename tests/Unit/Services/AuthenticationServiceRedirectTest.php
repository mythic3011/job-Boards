<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class AuthenticationServiceRedirectTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createPermissionTables();
    }

    public function test_admin_with_system_scope_redirects_to_admin_dashboard(): void
    {
        $admin = $this->createAdminUser();
        $this->grantPermission($admin, 'admin.system.view');

        $redirect = app(AuthenticationService::class)->getPostLoginRedirect($admin);

        $this->assertSame(route('admin.dashboard'), $redirect);
    }

    public function test_admin_without_system_scope_redirects_to_first_permitted_admin_surface(): void
    {
        $admin = $this->createAdminUser();
        $this->grantPermission($admin, 'admin.users.view');

        $redirect = app(AuthenticationService::class)->getPostLoginRedirect($admin);

        $this->assertSame(route('admin.users.index'), $redirect);
    }

    public function test_admin_without_admin_permissions_falls_back_to_home(): void
    {
        $admin = $this->createAdminUser();

        $redirect = app(AuthenticationService::class)->getPostLoginRedirect($admin);

        $this->assertSame(route('home'), $redirect);
    }

    private function createAdminUser(): User
    {
        $user = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'idcode' => 'user_' . \Illuminate\Support\Str::uuid(),
            'nickname' => 'Admin User',
            'login_id' => 'admin_' . \Illuminate\Support\Str::random(8),
            'email' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8)) . '@example.test',
            'password' => Hash::make('StrongPass123!'),
            'user_type' => 'company',
        ]);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function grantPermission(User $user, string $permission): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => $permission, 'guard_name' => 'web'],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $permissionId = DB::table('permissions')
            ->where('name', $permission)
            ->where('guard_name', 'web')
            ->value('id');

        DB::table('model_has_permissions')->updateOrInsert([
            'permission_id' => $permissionId,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
