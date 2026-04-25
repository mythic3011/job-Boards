<?php

namespace Tests\Unit\Actions;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use App\Services\PasswordLifecycleService;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class PasswordLifecycleDelegationTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function update_user_password_delegates_to_password_lifecycle_owner(): void
    {
        $user = User::factory()->create([
            'idcode' => 'user_'.Str::uuid(),
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        ]);

        $payload = [
            'current_password' => 'CurrentPass123!',
            'password' => 'N3wStrong!Pass123',
            'password_confirmation' => 'N3wStrong!Pass123',
            'two_factor_code' => '123456',
        ];

        $owner = Mockery::mock(PasswordLifecycleService::class);
        $owner->shouldReceive('updateViaFortify')
            ->once()
            ->with($user, $payload);

        $action = new UpdateUserPassword($owner);
        $action->update($user, $payload);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function reset_user_password_delegates_to_password_lifecycle_owner(): void
    {
        $user = User::factory()->create([
            'idcode' => 'user_'.Str::uuid(),
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        ]);

        $payload = [
            'password' => 'N3wStrong!Pass123',
            'password_confirmation' => 'N3wStrong!Pass123',
            'two_factor_code' => '123456',
        ];

        $owner = Mockery::mock(PasswordLifecycleService::class);
        $owner->shouldReceive('resetViaFortify')
            ->once()
            ->with($user, $payload);

        $action = new ResetUserPassword($owner);
        $action->reset($user, $payload);
        $this->addToAssertionCount(1);
    }
}
