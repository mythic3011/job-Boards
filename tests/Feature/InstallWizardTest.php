<?php

namespace Tests\Feature;

use App\Livewire\Install\Wizard;
use App\Services\InstallService;
use Livewire\Livewire;
use Mockery;
use PragmaRX\Google2FALaravel\Google2FA;
use RuntimeException;
use Tests\TestCase;

class InstallWizardTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_install_wizard_loads_system_checks_and_blocks_progress_when_a_requirement_fails(): void
    {
        $installService = Mockery::mock(InstallService::class);
        $installService->shouldReceive('runSystemChecks')->andReturn([
            'database' => true,
            'storage' => true,
            'cache' => false,
        ]);
        $this->app->instance(InstallService::class, $installService);

        Livewire::test(Wizard::class)
            ->set('username', 'adminuser')
            ->set('name', 'Admin User')
            ->set('email', 'admin@gmail.com')
            ->set('password', 'StrongPass123!')
            ->set('password_confirmation', 'StrongPass123!')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->assertSet('systemChecks.cache', false)
            ->set('app_name', 'Jobs Board')
            ->set('app_url', 'https://jobboard.example.com')
            ->set('timezone', 'Asia/Hong_Kong')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->assertSet('error', 'Fix the failed system requirements before continuing.');
    }

    public function test_install_wizard_generates_a_real_qr_and_advances_to_review_after_valid_otp(): void
    {
        $installService = Mockery::mock(InstallService::class);
        $installService->shouldReceive('runSystemChecks')->andReturn([
            'database' => true,
            'storage' => true,
            'cache' => true,
        ]);
        $this->app->instance(InstallService::class, $installService);

        $component = Livewire::test(Wizard::class)
            ->set('username', 'adminuser')
            ->set('name', 'Admin User')
            ->set('email', 'admin@gmail.com')
            ->set('password', 'StrongPass123!')
            ->set('password_confirmation', 'StrongPass123!')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->set('app_name', 'Jobs Board')
            ->set('app_url', 'https://jobboard.example.com')
            ->set('timezone', 'Asia/Hong_Kong')
            ->call('nextStep')
            ->assertSet('currentStep', 3)
            ->assertSet('error', '')
            ->assertSet('testSuccess', false)
            ->assertSee('Secure Your Account')
            ->assertSee('Save your recovery codes');

        $qrCodeSvg = $component->get('qrCodeDataUrl');
        $this->assertStringStartsWith('<?xml version="1.0"', $qrCodeSvg);
        $this->assertStringNotContainsString('Use the manual entry key below', $qrCodeSvg);

        $secret = $component->get('twoFactorSecret');
        $otp = app(Google2FA::class)->getCurrentOtp($secret);

        $component
            ->set('testCode', $otp)
            ->call('testOTP')
            ->call('nextStep')
            ->assertSet('currentStep', 4)
            ->assertSet('testSuccess', true)
            ->assertSet('testResult', 'Valid code! 2FA is working correctly.');
    }

    public function test_install_wizard_verifies_a_valid_otp_code_without_throwing(): void
    {
        $installService = Mockery::mock(InstallService::class);
        $installService->shouldReceive('runSystemChecks')->andReturn([
            'database' => true,
            'storage' => true,
            'cache' => true,
        ]);
        $this->app->instance(InstallService::class, $installService);

        $component = Livewire::test(Wizard::class)
            ->set('username', 'adminuser')
            ->set('name', 'Admin User')
            ->set('email', 'admin@gmail.com')
            ->set('password', 'StrongPass123!')
            ->set('password_confirmation', 'StrongPass123!')
            ->call('nextStep')
            ->set('app_name', 'Jobs Board')
            ->set('app_url', 'https://jobboard.example.com')
            ->set('timezone', 'Asia/Hong_Kong')
            ->call('nextStep')
            ->assertSet('currentStep', 3);

        $secret = $component->get('twoFactorSecret');
        $otp = app(Google2FA::class)->getCurrentOtp($secret);

        $component
            ->set('testCode', $otp)
            ->call('testOTP')
            ->assertSet('testSuccess', true)
            ->assertSet('testResult', 'Valid code! 2FA is working correctly.');
    }

    public function test_install_wizard_exposes_recovery_code_download_href_as_a_data_url(): void
    {
        $component = Livewire::test(Wizard::class)
            ->set('app_name', 'Jobs Board')
            ->set('email', 'admin@gmail.com')
            ->set('recoveryCodes', ['RCODE-1', 'RCODE-2']);

        $downloadHref = $component->get('recoveryCodesDownloadHref');

        $this->assertStringStartsWith('data:text/plain;charset=utf-8;base64,', $downloadHref);

        $encodedPayload = substr($downloadHref, strlen('data:text/plain;charset=utf-8;base64,'));
        $decodedPayload = base64_decode($encodedPayload, true);

        $this->assertIsString($decodedPayload);
        $this->assertStringContainsString('Jobs Board - Recovery Codes', $decodedPayload);
        $this->assertStringContainsString('Generated for: admin@gmail.com', $decodedPayload);
        $this->assertStringContainsString('1. RCODE-1', $decodedPayload);
        $this->assertStringContainsString('2. RCODE-2', $decodedPayload);
    }

    public function test_install_wizard_stays_on_system_step_when_two_factor_generation_fails(): void
    {
        $installService = Mockery::mock(InstallService::class);
        $installService->shouldReceive('runSystemChecks')->andReturn([
            'database' => true,
            'storage' => true,
            'cache' => true,
        ]);
        $this->app->instance(InstallService::class, $installService);

        $google2fa = Mockery::mock(Google2FA::class);
        $google2fa->shouldReceive('generateSecretKey')->andThrow(new RuntimeException('qr broken'));
        $this->app->instance(Google2FA::class, $google2fa);

        Livewire::test(Wizard::class)
            ->set('username', 'adminuser')
            ->set('name', 'Admin User')
            ->set('email', 'admin@gmail.com')
            ->set('password', 'StrongPass123!')
            ->set('password_confirmation', 'StrongPass123!')
            ->call('nextStep')
            ->set('app_name', 'Jobs Board')
            ->set('app_url', 'https://jobboard.example.com')
            ->set('timezone', 'Asia/Hong_Kong')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->assertSet('twoFactorSecret', '')
            ->assertSet('qrCodeDataUrl', '')
            ->assertSet('recoveryCodes', [])
            ->assertSet('error', 'Installation setup failed. Fix the issue and try again.');
    }

    public function test_install_wizard_masks_internal_error_when_completion_fails(): void
    {
        $installService = Mockery::mock(InstallService::class);
        $installService->shouldReceive('completeInstallation')
            ->once()
            ->andThrow(new RuntimeException('Admin role not found.'));
        $this->app->instance(InstallService::class, $installService);

        Livewire::test(Wizard::class)
            ->set('username', 'adminuser')
            ->set('name', 'Admin User')
            ->set('email', 'admin@gmail.com')
            ->set('password', 'StrongPass123!')
            ->set('password_confirmation', 'StrongPass123!')
            ->set('app_name', 'Jobs Board')
            ->set('app_url', 'https://jobboard.example.com')
            ->set('timezone', 'Asia/Hong_Kong')
            ->set('checksLoaded', true)
            ->set('systemChecks', [
                'database' => true,
                'storage' => true,
                'cache' => true,
            ])
            ->set('twoFactorSecret', 'JBSWY3DPEHPK3PXP')
            ->set('recoveryCodes', ['RCODE-1'])
            ->set('testSuccess', true)
            ->call('complete')
            ->assertSet('processing', false)
            ->assertSet('error', 'Installation failed. Please try again.')
            ->assertDontSee('Admin role not found.');
    }
}
