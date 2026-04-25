<?php

namespace Tests\Feature\Profile;

use App\Livewire\Profile\TwoFactor;
use App\Models\User;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class TwoFactorConfirmFailureTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createAuditLogsTable();
    }

    public function test_confirm_failure_does_not_flash_success(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->individual()->create([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['CODE-ONE'])),
        ]);

        $validCode = $google2fa->getCurrentOtp($secret);

        $this->mock(ConfirmTwoFactorAuthentication::class, function ($mock): void {
            $mock->shouldReceive('__invoke')
                ->once()
                ->andThrow(new \RuntimeException('simulated Fortify failure'));
        });

        Livewire::actingAs($user)
            ->test(TwoFactor::class)
            ->set('verificationCode', $validCode)
            ->assertSet('codeIsValid', false)
            ->assertHasErrors(['verificationCode']);

        $this->assertNull(session('success'));
    }

    public function test_missing_persisted_confirmation_does_not_activate_pending_registration(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->individual()->create([
            'registration_state' => 'pending_2fa',
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['CODE-ONE'])),
        ]);

        $validCode = $google2fa->getCurrentOtp($secret);

        $this->mock(ConfirmTwoFactorAuthentication::class, function ($mock): void {
            $mock->shouldReceive('__invoke')
                ->once()
                ->andReturnNull();
        });

        Livewire::actingAs($user)
            ->test(TwoFactor::class)
            ->set('verificationCode', $validCode)
            ->assertSet('codeIsValid', false)
            ->assertHasErrors(['verificationCode']);

        $this->assertSame('pending_2fa', $user->fresh()->registration_state);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNull(session('success'));
    }
}
