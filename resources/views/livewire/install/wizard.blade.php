<?php

use App\Services\InstallService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\RecoveryCode;
use Livewire\Volt\Component;
use PragmaRX\Google2FA\Google2FA;

new class extends Component
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
            
            // Generate QR code data URL using a simple SVG QR generator
            $this->qrCodeDataUrl = $this->generateQRCodeDataUrl($qrCodeUrl);
            
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
            $response = \Illuminate\Support\Facades\Http::post(route('install.complete'), [
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
     * Generate QR code data URL (simple implementation)
     */
    protected function generateQRCodeDataUrl(string $text): string
    {
        // For production, use a proper QR code library
        // This is a placeholder that works with the JavaScript QR code library
        return "data:image/svg+xml;base64," . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">' .
            '<rect width="256" height="256" fill="#ffffff"/>' .
            '<text x="128" y="128" text-anchor="middle" font-size="12" fill="#666">' .
            'Scan with authenticator app' .
            '</text></svg>'
        );
    }

    /**
     * Get formatted secret key for display
     */
    public function getFormattedSecretProperty(): string
    {
        return chunk_split($this->twoFactorSecret, 4, ' ');
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-indigo-50 to-blue-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-600 rounded-2xl mb-4 shadow-lg">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Setup Your Job Board</h1>
            <p class="text-gray-600">Let's get everything configured in just a few steps</p>
        </div>

        <!-- Step Indicators -->
        <div class="flex justify-between mb-8 max-w-md mx-auto">
            @foreach([1 => 'Account', 2 => 'System', 3 => 'Security', 4 => 'Review'] as $step => $label)
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full border-2 flex items-center justify-center font-semibold text-sm transition-all duration-200 
                        @if($currentStep > $step) bg-green-500 text-white border-green-500
                        @elseif($currentStep === $step) bg-indigo-600 text-white border-indigo-600 ring-4 ring-indigo-200
                        @else bg-white text-gray-400 border-gray-300
                        @endif">
                        @if($currentStep > $step)
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        @else
                            {{ $step }}
                        @endif
                    </div>
                    <span class="text-xs mt-2 font-medium 
                        @if($currentStep === $step) text-indigo-600
                        @elseif($currentStep > $step) text-green-600
                        @else text-gray-400
                        @endif">{{ $label }}</span>
                </div>
            @endforeach
        </div>

        <!-- Error Message -->
        @if($error)
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                {{ $error }}
            </div>
        @endif

        <!-- Step Content -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            @if($currentStep === 1)
                @include('livewire.install.steps.account')
            @elseif($currentStep === 2)
                @include('livewire.install.steps.system')
            @elseif($currentStep === 3)
                @include('livewire.install.steps.security')
            @elseif($currentStep === 4)
                @include('livewire.install.steps.review')
            @endif
        </div>
    </div>
</div>
