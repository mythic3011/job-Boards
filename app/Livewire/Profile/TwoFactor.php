<?php

namespace App\Livewire\Profile;

use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class TwoFactor extends Component
{
    public string $verificationCode = '';
    public bool $autoVerifying = false;
    public bool $codeIsValid = false;

    protected $rules = [
        'verificationCode' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
    ];

    protected $messages = [
        'verificationCode.required' => 'Please enter the verification code.',
        'verificationCode.size' => 'The verification code must be exactly 6 digits.',
        'verificationCode.regex' => 'The verification code must contain only numbers.',
    ];

    public function enable2FA(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        app(TwoFactorService::class)->enable($user);
        $user->refresh();

        $this->dispatch('2fa-enabled');
    }

    public function confirm2FA(): void
    {
        $this->validate();

        $user = Auth::user();
        if (!$user) {
            return;
        }

        if (!app(TwoFactorService::class)->confirm($user, $this->verificationCode)) {
            $this->addError('verificationCode', 'The verification code is incorrect. Please try again.');
            return;
        }

        session()->flash('success', '🎉 Two-factor authentication is now active! Your account is more secure.');
        $this->verificationCode = '';
    }

    public function updatedVerificationCode(string $value): void
    {
        $value = trim($value);
        $this->codeIsValid = false;
        $this->resetErrorBag();

        if (strlen($value) !== 6 || !preg_match('/^\d{6}$/', $value)) {
            return;
        }

        if ($this->autoVerifying) {
            return;
        }

        // Rate limiting: 5 attempts per minute
        $key = '2fa-verify:' . Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('verificationCode', "Too many attempts. Try again in {$seconds} seconds.");
            return;
        }

        $this->autoVerifying = true;

        try {
            $user = Auth::user();
            if (!$user) {
                return;
            }

            // Hit rate limiter BEFORE verification
            RateLimiter::hit($key, 60);

            $confirmed = app(TwoFactorService::class)->confirm($user, $value);

            if ($confirmed) {
                // Clear rate limiter on success
                RateLimiter::clear($key);

                $this->codeIsValid = true;

                // Refresh user to get updated 2FA status
                $user->refresh();

                // Verify 2FA is actually enabled
                if ($user->two_factor_confirmed_at) {
                    session()->flash('success', '🎉 Two-factor authentication is now active! Your account is more secure.');
                    $this->js('setTimeout(() => window.location.href = "' . route('profile.show') . '", 800);');
                } else {
                    $this->codeIsValid = false;
                    $this->addError('verificationCode', 'Failed to confirm 2FA. Please try again.');
                }
            } else {
                $this->codeIsValid = false;
                $this->addError('verificationCode', 'The verification code is incorrect. Please try again.');
            }
        } catch (\Exception $e) {
            $this->codeIsValid = false;
            $this->addError('verificationCode', 'An error occurred. Please try again.');
            \Log::error('2FA verification error', ['error' => $e->getMessage()]);
        } finally {
            $this->autoVerifying = false;
        }
    }

    public function disable2FA(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        app(TwoFactorService::class)->disable($user);
        session()->flash('success', 'Two-factor authentication has been disabled.');
    }

    public function cancel2FA(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        app(TwoFactorService::class)->cancelSetup($user);
        $this->verificationCode = '';
    }

    public function regenerateRecoveryCodes(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        app(TwoFactorService::class)->regenerateRecoveryCodes($user);
        session()->flash('success', 'New recovery codes have been generated.');
    }

    public function render()
    {
        $user = Auth::user()?->fresh();

        if (!$user) {
            return view('livewire.profile.two-factor', [
                'user' => null,
                'is2FAEnabled' => false,
                'isSettingUp2FA' => false,
                'recoveryCodes' => [],
                'codeIsValid' => false,
            ])->layout('layouts.app')->title('Security Settings');
        }

        $twoFactorService = app(TwoFactorService::class);
        $is2FAEnabled = $twoFactorService->isEnabled($user);
        $isSettingUp2FA = $twoFactorService->isSetupInProgress($user);
        $recoveryCodes = $twoFactorService->getRecoveryCodes($user);

        return view('livewire.profile.two-factor', [
            'user' => $user,
            'is2FAEnabled' => $is2FAEnabled,
            'isSettingUp2FA' => $isSettingUp2FA,
            'recoveryCodes' => $recoveryCodes,
            'codeIsValid' => $this->codeIsValid,
        ])->layout('layouts.app')->title('Security Settings');
    }
}