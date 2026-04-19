<?php

namespace App\Livewire\Install;

use App\Services\InstallService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\RecoveryCode;
use Livewire\Component;
use PragmaRX\Google2FALaravel\Google2FA;
use Throwable;

class Wizard extends Component
{
    public int $currentStep = 1;

    // Step 1: Admin Account
    public string $username = '';
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    // Step 2: System Configuration
    public string $app_name = 'Jobs Board';
    public string $app_url = '';
    public string $timezone = 'Asia/Hong_Kong';
    public array $systemChecks = [];
    public bool $checksLoaded = false;
    public string $checksError = '';

    // Step 3: Two-Factor Authentication
    public string $twoFactorSecret = '';
    public array $recoveryCodes = [];
    public string $qrCodeDataUrl = '';
    public string $testCode = '';
    public string $testResult = '';
    public bool $testSuccess = false;

    // Step 4: Review & Complete
    public bool $installDemo = false;

    public bool $processing = false;
    public string $error = '';

    public function mount(): void
    {
        $this->app_url = url('/');
    }

    public function nextStep(): void
    {
        $this->error = '';

        try {
            match($this->currentStep) {
                1 => $this->validateStep1(),
                2 => $this->validateStep2(),
                3 => $this->validateStep3(),
                default => null,
            };

            if ($this->currentStep < 4) {
                $this->currentStep++;

                if ($this->currentStep === 2) {
                    $this->refreshSystemChecks();
                }

                if ($this->currentStep === 3) {
                    $this->generate2FA();
                }
            }
        } catch (ValidationException $e) {
            $this->error = collect($e->errors())->flatten()->first();
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
        $this->error = '';
    }

    protected function validateStep1(): void
    {
        $this->validate([
            'username' => ['required', 'string', 'min:3', 'max:255', 'regex:/^[a-zA-Z0-9_]+$/'],
            'name'     => ['required', 'string', 'min:2', 'max:255'],
            'email'    => ['required', 'email:rfc,dns', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ], [
            'username.regex' => 'Username can only contain letters, numbers, and underscores.',
            'password.min'   => 'Password must be at least 12 characters.',
        ]);
    }

    protected function validateStep2(): void
    {
        $this->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_url'  => ['required', 'url', 'max:255'],
            'timezone' => ['required', 'string', 'max:255'],
        ]);

        $this->ensureSystemChecksPass();
    }

    protected function validateStep3(): void
    {
        if (empty($this->twoFactorSecret)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'twoFactorSecret' => '2FA setup is required for admin accounts.',
            ]);
        }

        if (!$this->testSuccess) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'testCode' => 'You must verify your 2FA code before continuing.',
            ]);
        }
    }

    public function generate2FA(): void
    {
        $this->resetTwoFactorState();

        try {
            $google2fa = app(Google2FA::class);
            $this->twoFactorSecret = $google2fa->generateSecretKey();
            $this->qrCodeDataUrl = $this->generateQRCodeSVG();

            $this->recoveryCodes = collect(range(1, 10))
                ->map(fn() => RecoveryCode::generate())
                ->toArray();

        } catch (Throwable $e) {
            $this->currentStep = 2;
            $this->resetTwoFactorState();
            $this->error = 'Installation setup failed. Fix the issue and try again.';

            Log::error('2FA generation failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function generateQRCodeSVG(): string
    {
        return app(Google2FA::class)->getQRCodeInline(
            $this->app_name,
            $this->email,
            $this->twoFactorSecret
        );
    }

    public function testOTP(): void
    {
        $this->testResult = '';
        $this->testSuccess = false;

        if (empty($this->testCode) || !preg_match('/^\d{6}$/', $this->testCode)) {
            $this->testResult = 'Please enter a valid 6-digit code.';
            return;
        }

        try {
            $google2fa = app(Google2FA::class);
            $valid = $google2fa->verifyKey($this->twoFactorSecret, $this->testCode);

            if ($valid) {
                $this->testSuccess = true;
                $this->testResult = 'Valid code! 2FA is working correctly.';
            } else {
                $this->testResult = 'Invalid code. Please check your authenticator app.';
            }
        } catch (Throwable $e) {
            $this->testResult = 'Verification failed. Please try again.';
        }

        $this->testCode = '';
    }

    public function downloadRecoveryCodes(): string
    {
        $lines = [
            "{$this->app_name} - Recovery Codes",
            "Generated for: {$this->email}",
            "Generated on: " . now()->toDateTimeString(),
            "",
            "IMPORTANT: Store these codes securely. Each code can only be used once.",
            "",
            "Recovery Codes:",
        ];

        foreach ($this->recoveryCodes as $i => $code) {
            $lines[] = ($i + 1) . ". " . $code;
        }

        return implode("\n", $lines);
    }

    public function complete(): void
    {
        $this->processing = true;
        $this->error = '';

        try {
            $this->validateStep1();
            $this->validateStep2();
            $this->validateStep3();

            app(InstallService::class)->completeInstallation([
                'admin_name'         => $this->name,
                'admin_email'        => $this->email,
                'admin_password'     => $this->password,
                'two_factor_secret'  => $this->twoFactorSecret,
                'recovery_codes'     => $this->recoveryCodes,
                'app_name'           => $this->app_name,
                'app_url'            => $this->app_url,
                'timezone'           => $this->timezone,
                'install_demo_data'  => $this->installDemo,
            ]);

            session()->flash('success', 'Installation completed successfully!');
            $this->redirect('/login', navigate: true);

        } catch (ValidationException $e) {
            $this->processing = false;
            $this->error = collect($e->errors())->flatten()->first();
        } catch (Throwable $e) {
            $this->processing = false;
            $this->error = 'Installation failed. Please try again.';

            Log::error('Installation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function refreshSystemChecks(): void
    {
        $this->checksError = '';

        try {
            $checks = app(InstallService::class)->runSystemChecks();

            $this->systemChecks = [
                'database' => ($checks['database'] ?? false) === true,
                'storage' => ($checks['storage'] ?? false) === true,
                'cache' => ($checks['cache'] ?? false) === true,
            ];
            $this->checksLoaded = true;
        } catch (Throwable $e) {
            $this->systemChecks = [
                'database' => false,
                'storage' => false,
                'cache' => false,
            ];
            $this->checksLoaded = true;
            $this->checksError = 'System requirements could not be checked.';

            Log::error('Install system checks failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getSystemRequirementsPassingProperty(): bool
    {
        if (! $this->checksLoaded || $this->checksError !== '') {
            return false;
        }

        foreach (['database', 'storage', 'cache'] as $check) {
            if (($this->systemChecks[$check] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    public function getFormattedSecretProperty(): string
    {
        return chunk_split($this->twoFactorSecret, 4, ' ');
    }

    protected function ensureSystemChecksPass(): void
    {
        if (! $this->checksLoaded) {
            $this->refreshSystemChecks();
        }

        if (! $this->systemRequirementsPassing) {
            throw ValidationException::withMessages([
                'systemChecks' => 'Fix the failed system requirements before continuing.',
            ]);
        }
    }

    protected function resetTwoFactorState(): void
    {
        $this->twoFactorSecret = '';
        $this->recoveryCodes = [];
        $this->qrCodeDataUrl = '';
        $this->testCode = '';
        $this->testResult = '';
        $this->testSuccess = false;
    }

    public function render()
    {
        return view('livewire.install.wizard');
    }
}
