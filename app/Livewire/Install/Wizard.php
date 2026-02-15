<?php

namespace App\Livewire\Install;

use App\Services\InstallService;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\RecoveryCode;
use Livewire\Component;
use PragmaRX\Google2FALaravel\Google2FA;

class Wizard extends Component
{
    // Current step (1-4)
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

    // Step 3: Two-Factor Authentication
    public string $twoFactorSecret = '';
    public array $recoveryCodes = [];
    public string $qrCodeDataUrl = '';
    public string $testCode = '';
    public string $testResult = '';
    public bool $testSuccess = false;

    // Step 4: Review & Complete
    public bool $installDemo = false;

    // Helper properties
    public bool $processing = false;
    public string $error = '';

    public function mount(): void
    {
        $this->app_url = url('/');
    }

    /**
     * Move to next step with validation
     */
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

                // Generate 2FA when entering step 3
                if ($this->currentStep === 3) {
                    $this->generate2FA();
                }
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->error = collect($e->errors())->flatten()->first();
        }
    }

    /**
     * Move to previous step
     */
    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
        $this->error = '';
    }

    /**
     * Validate Step 1: Admin Account
     */
    protected function validateStep1(): void
    {
        $this->validate([
            'username' => ['required', 'string', 'min:3', 'max:255', 'regex:/^[a-zA-Z0-9_]+$/'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc,dns', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ], [
            'username.regex' => 'Username can only contain letters, numbers, and underscores.',
            'password.min' => 'Password must be at least 12 characters.',
        ]);
    }

    /**
     * Validate Step 2: System Configuration
     */
    protected function validateStep2(): void
    {
        $this->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
            'timezone' => ['required', 'string', 'max:255'],
        ]);
    }

    /**
     * Validate Step 3: 2FA Setup
     */
    protected function validateStep3(): void
    {
        // 2FA is required, just ensure it's set up
        if (empty($this->twoFactorSecret)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'twoFactorSecret' => '2FA setup is required for admin accounts.'
            ]);
        }
    }

    /**
     * Generate 2FA secret and QR code
     */
    public function generate2FA(): void
    {
        try {
            $google2fa = new Google2FA();
            $this->twoFactorSecret = $google2fa->generateSecretKey();

            // Generate QR code URL
            $qrCodeUrl = $google2fa->getQRCodeUrl(
                $this->app_name,
                $this->email,
                $this->twoFactorSecret
            );

            // Generate QR code SVG using chillerlan/php-qrcode or similar
            // For now, use a data URL approach that works with the JavaScript library
            $this->qrCodeDataUrl = $this->generateQRCodeSVG($qrCodeUrl);

            // Generate recovery codes
            $this->recoveryCodes = collect(range(1, 10))
                ->map(fn() => RecoveryCode::generate())
                ->toArray();

        } catch (\Exception $e) {
            $this->error = 'Failed to generate 2FA codes. Please try again.';
            \Illuminate\Support\Facades\Log::error('2FA generation failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate QR code SVG
     */
    protected function generateQRCodeSVG(string $text): string
    {
        // Use a simple approach - encode the URL and let the frontend JavaScript handle it
        // Or use a basic SVG placeholder that instructs to use the manual key
        return '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256" class="w-48 h-48">' .
               '<rect width="256" height="256" fill="#f3f4f6"/>' .
               '<text x="128" y="120" text-anchor="middle" font-size="14" fill="#374151" font-family="sans-serif">' .
               'Use the manual entry key below' .
               '</text>' .
               '<text x="128" y="145" text-anchor="middle" font-size="12" fill="#6b7280" font-family="sans-serif">' .
               'to add to your authenticator app' .
               '</text>' .
               '</svg>';
    }

    /**
     * Test 2FA code
     */
    public function testOTP(): void
    {
        $this->testResult = '';
        $this->testSuccess = false;

        if (empty($this->testCode) || !preg_match('/^\d{6}$/', $this->testCode)) {
            $this->testResult = 'Please enter a valid 6-digit code.';
            return;
        }

        try {
            $google2fa = new Google2FA();
            $valid = $google2fa->verifyKey($this->twoFactorSecret, $this->testCode);

            if ($valid) {
                $this->testSuccess = true;
                $this->testResult = 'Valid code! 2FA is working correctly.';
            } else {
                $this->testResult = 'Invalid code. Please check your authenticator app.';
            }
        } catch (\Exception $e) {
            $this->testResult = 'Verification failed. Please try again.';
        }

        $this->testCode = '';
    }

    /**
     * Copy recovery codes to clipboard (handled by Alpine.js)
     */
    public function downloadRecoveryCodes(): string
    {
        $content = [
            "{$this->app_name} - Recovery Codes",
            "Generated for: {$this->email}",
            "Generated on: " . now()->toDateTimeString(),
            "",
            "IMPORTANT: Store these codes securely.",
            "Each code can only be used once.",
            "",
            "Recovery Codes:",
        ];

        foreach ($this->recoveryCodes as $index => $code) {
            $content[] = ($index + 1) . ". " . $code;
        }

        return implode("\n", $content);
    }

    /**
     * Complete installation
     */
    public function complete(): void
    {
        $this->processing = true;
        $this->error = '';

        try {
            // Final validation
            $this->validateStep1();
            $this->validateStep2();
            $this->validateStep3();

            // Send data to controller endpoint via HTTP
            $response = \Illuminate\Support\Facades\Http::asJson()->post(route('install.complete'), [
                'admin_name' => $this->name,
                'admin_email' => $this->email,
                'admin_password' => $this->password,
                'admin_password_confirmation' => $this->password_confirmation,
                'two_factor_secret' => $this->twoFactorSecret,
                'recovery_codes' => $this->recoveryCodes,
                'app_name' => $this->app_name,
                'app_url' => $this->app_url,
                'timezone' => $this->timezone,
                'demo' => $this->installDemo,
            ]);

            if ($response->successful()) {
                session()->flash('success', 'Installation completed successfully!');
                $this->redirect('/login', navigate: true);
            } else {
                $errorData = $response->json();
                $this->error = $errorData['message'] ?? 'Installation failed. Please try again.';
                $this->processing = false;
            }

        } catch (\Exception $e) {
            $this->processing = false;
            $this->error = 'Installation failed: ' . $e->getMessage();
            \Illuminate\Support\Facades\Log::error('Installation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get formatted secret key for display
     */
    public function getFormattedSecretProperty(): string
    {
        return chunk_split($this->twoFactorSecret, 4, ' ');
    }

    public function render()
    {
        return view('livewire.install.wizard');
    }
}
