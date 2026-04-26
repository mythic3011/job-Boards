<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createPermissionTables();

        DB::table('permissions')->insert([
            ['name' => 'admin.users.delete', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin.users.force_password_reset', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_delete_denies_self_target_even_with_delete_permission(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $user->givePermissionTo('admin.users.delete');

        $response = (new UserPolicy())->delete($user, $user);

        $this->assertFalse($response->allowed());
        $this->assertSame('You cannot delete your own account.', $response->message());
    }

    public function test_force_password_reset_requires_matching_permission(): void
    {
        $actor = User::factory()->create(['user_type' => 'admin']);
        $target = User::factory()->individual()->create();

        $this->assertFalse((new UserPolicy())->forcePasswordReset($actor, $target));

        $actor->givePermissionTo('admin.users.force_password_reset');

        $this->assertTrue((new UserPolicy())->forcePasswordReset($actor, $target));
    }
}
