<?php

namespace App\Livewire\Profile;

use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
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

        $twoFactorService = app(TwoFactorService::class);
        $twoFactorService->enable($user);
        $user->refresh();

        $this->dispatch('2fa-enabled');
    }

    public function confirm2FA(): void
    {
        $twoFactorService = app(TwoFactorService::class);
        $this->validate();

        $user = Auth::user();
        if (!$user) {
            return;
        }

        if (!$twoFactorService->confirm($user, $this->verificationCode)) {
            $this->addError('verificationCode', 'The verification code is incorrect. Please try again.');
            return;
        }

        session()->flash('success', '🎉 Two-factor authentication is now active! Your account is more secure.');
        $this->verificationCode = '';
    }

    public function updatedVerificationCode(string $value)
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

        $this->autoVerifying = true;

        try {
            $user = Auth::user();
            if (!$user) {
                return;
            }

            $twoFactorService = app(TwoFactorService::class);
            
            \Log::info('2FA verification attempt', [
                'user_id' => $user->id,
                'code_length' => strlen($value),
                'has_secret' => !empty($user->two_factor_secret),
                'has_confirmed_at' => !empty($user->two_factor_confirmed_at),
            ]);
            
            // Don't verify twice - just call confirm which does its own verification
            try {
                $confirmed = $twoFactorService->confirm($user, $value);
                \Log::info('2FA confirm result', ['confirmed' => $confirmed]);
                
                if ($confirmed) {
                    $this->codeIsValid = true;
                    
                    // Refresh user to get updated 2FA status
                    $user->refresh();
                    
                    \Log::info('2FA user refreshed', [
                        'confirmed_at' => $user->two_factor_confirmed_at?->toDateTimeString(),
                    ]);
                    
                    // Verify 2FA is actually enabled
                    if ($user->two_factor_confirmed_at) {
                        // Flash success message
                        session()->flash('success', '🎉 Two-factor authentication is now active! Your account is more secure.');
                        
                        // Use JS to redirect after a brief delay
                        $this->js('setTimeout(() => window.location.href = "' . route('profile.show') . '", 800);');
                    } else {
                        $this->codeIsValid = false;
                        $this->addError('verificationCode', 'Failed to confirm 2FA. Please try again.');
                        \Log::error('2FA confirmation failed - no confirmed_at timestamp');
                    }
                } else {
                    $this->codeIsValid = false;
                    $this->addError('verificationCode', 'The verification code is incorrect. Please try again.');
                    \Log::error('2FA confirm returned false');
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->codeIsValid = false;
                $this->addError('verificationCode', 'The verification code is incorrect. Please try again.');
                \Log::error('2FA validation exception', ['error' => $e->getMessage()]);
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

        $twoFactorService = app(TwoFactorService::class);
        $twoFactorService->disable($user);
        session()->flash('success', 'Two-factor authentication has been disabled.');
    }

    public function cancel2FA(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $twoFactorService = app(TwoFactorService::class);
        $twoFactorService->cancelSetup($user);
        $this->verificationCode = '';
    }

    public function regenerateRecoveryCodes(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $twoFactorService = app(TwoFactorService::class);
        $twoFactorService->regenerateRecoveryCodes($user);
        session()->flash('success', 'New recovery codes have been generated.');
    }

    public function render()
    {
        $twoFactorService = app(TwoFactorService::class);
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