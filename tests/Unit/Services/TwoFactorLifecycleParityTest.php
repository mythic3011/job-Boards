<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\TwoFactorService;
use PragmaRX\Google2FALaravel\Google2FA;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class TwoFactorLifecycleParityTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createAuditLogsTable();
    }

    public function test_install_and_profile_flows_persist_equivalent_confirmed_two_factor_state(): void
    {
        /** @var TwoFactorService $service */
        $service = app(TwoFactorService::class);

        $installUser = User::factory()->individual()->create([
            'email' => 'install@example.com',
        ]);

        $profileUser = User::factory()->individual()->create([
            'email' => 'profile@example.com',
        ]);

        $provisionedSecret = $service->generateSetupSecret();
        $provisionedCodes = $service->generateRecoveryCodes(8);
        $service->applyProvisionedSetup($installUser, $provisionedSecret, $provisionedCodes);

        $service->enable($profileUser);
        $profileUser->refresh();
        $this->assertNotNull($profileUser->two_factor_secret);
        $this->assertNull($profileUser->two_factor_confirmed_at);

        $profileSecret = $service->getSecret($profileUser);
        $this->assertIsString($profileSecret);
        $this->assertNotSame('', $profileSecret);

        $otp = app(Google2FA::class)->getCurrentOtp($profileSecret);
        $this->assertTrue($service->confirm($profileUser, $otp));

        $installUser->refresh();
        $profileUser->refresh();

        // Both flows must end with the same DB contract: confirmed secret + recovery codes.
        $this->assertNotNull($installUser->two_factor_secret);
        $this->assertNotNull($profileUser->two_factor_secret);
        $this->assertNotNull($installUser->two_factor_confirmed_at);
        $this->assertNotNull($profileUser->two_factor_confirmed_at);
        $this->assertNotNull($installUser->two_factor_recovery_codes);
        $this->assertNotNull($profileUser->two_factor_recovery_codes);

        $installCodes = $service->getRecoveryCodes($installUser);
        $profileCodes = $service->getRecoveryCodes($profileUser);

        $this->assertCount(8, $installCodes);
        $this->assertCount(8, $profileCodes);
        $this->assertIsString($installCodes[0] ?? null);
        $this->assertIsString($profileCodes[0] ?? null);
    }
}

