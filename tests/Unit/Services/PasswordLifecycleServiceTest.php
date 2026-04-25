<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\PasswordLifecycleService;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class PasswordLifecycleServiceTest extends TestCase
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
    public function set_password_is_the_mutation_owner_for_password_hashing(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldStrongPass123!'),
        ]);

        $twoFactorService = Mockery::mock(TwoFactorService::class);
        $provider = Mockery::mock(TwoFactorAuthenticationProvider::class);
        $service = new PasswordLifecycleService($twoFactorService, $provider);

        $service->setPassword($user, 'N3wStrongPass!123');

        $user->refresh();

        $this->assertTrue(Hash::check('N3wStrongPass!123', $user->password));
    }

    #[Test]
    public function assert_reset_allowed_requires_two_factor_code_for_non_admin_reset_when_2fa_enabled(): void
    {
        $user = User::factory()->create([
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        ]);

        $twoFactorService = Mockery::mock(TwoFactorService::class);
        $twoFactorService->shouldReceive('isEnabled')->with($user)->andReturnTrue();
        $provider = Mockery::mock(TwoFactorAuthenticationProvider::class);
        $service = new PasswordLifecycleService($twoFactorService, $provider);

        $this->expectException(ValidationException::class);
        $service->assertResetAllowed($user, null, false);
    }
}
