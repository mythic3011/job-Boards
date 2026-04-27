<?php

namespace Tests\Feature\Profile;

use App\Livewire\Profile\TwoFactor;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Livewire\Livewire;
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
        $secret = 'JBSWY3DPEHPK3PXP';

        $user = User::factory()->individual()->create([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['CODE-ONE'])),
        ]);

        $code = '111111';

        $this->mock(ConfirmTwoFactorAuthentication::class, function ($mock): void {
            $mock->shouldReceive('__invoke')
                ->once()
                ->andThrow(new \RuntimeException('simulated Fortify failure'));
        });

        RateLimiter::clear('2fa-verify:'.$user->id);

        Livewire::actingAs($user)
            ->test(TwoFactor::class)
            ->set('verificationCode', $code)
            ->assertSet('codeIsValid', false)
            ->assertHasErrors(['verificationCode']);

        $this->assertNull(session('success'));
    }

    public function test_missing_persisted_confirmation_does_not_activate_pending_registration(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';

        $user = User::factory()->individual()->create([
            'registration_state' => 'pending_2fa',
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['CODE-ONE'])),
        ]);

        $code = '111111';

        $this->mock(ConfirmTwoFactorAuthentication::class, function ($mock): void {
            $mock->shouldReceive('__invoke')
                ->once()
                ->andReturnNull();
        });

        RateLimiter::clear('2fa-verify:'.$user->id);

        Livewire::actingAs($user)
            ->test(TwoFactor::class)
            ->set('verificationCode', $code)
            ->assertSet('codeIsValid', false)
            ->assertHasErrors(['verificationCode']);

        $this->assertSame('pending_2fa', $user->fresh()->registration_state);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNull(session('success'));
    }
}
