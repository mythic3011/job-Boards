<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Livewire\Component;

class TwoFactor extends Component
{
    public string $verificationCode = '';

    protected $rules = [
        'verificationCode' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
    ];

    protected $messages = [
        'verificationCode.required' => 'Please enter the verification code.',
        'verificationCode.size' => 'The verification code must be exactly 6 digits.',
        'verificationCode.regex' => 'The verification code must contain only numbers.',
    ];

    public function enable2FA()
    {
        $user = Auth::user();
        
        if (!$user->two_factor_secret) {
            app(EnableTwoFactorAuthentication::class)($user);
            $this->dispatch('2fa-enabled');
        }
    }

    public function confirm2FA()
    {
        $this->validate();

        $user = Auth::user();
        
        if (!app(TwoFactorAuthenticationProvider::class)->verify(
            decrypt($user->two_factor_secret),
            $this->verificationCode
        )) {
            $this->addError('verificationCode', 'The verification code is incorrect. Please try again.');
            return;
        }

        app(ConfirmTwoFactorAuthentication::class)($user);
        
        session()->flash('success', '🎉 Two-factor authentication is now active! Your account is more secure.');
        $this->verificationCode = '';
    }

    public function disable2FA()
    {
        app(DisableTwoFactorAuthentication::class)(Auth::user());
        session()->flash('success', 'Two-factor authentication has been disabled.');
    }

    public function cancel2FA()
    {
        $user = Auth::user();
        if ($user->two_factor_secret && !$user->two_factor_confirmed_at) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
            ])->save();
        }
        $this->verificationCode = '';
    }

    public function regenerateRecoveryCodes()
    {
        $user = Auth::user();
        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode(
                collect(range(1, 8))->map(fn() => strtoupper(Str::random(5) . '-' . Str::random(5)))->toArray()
            )),
        ])->save();
        
        session()->flash('success', 'New recovery codes have been generated.');
    }

    public function render()
    {
        $user = Auth::user();
        $is2FAEnabled = $user->two_factor_confirmed_at !== null;
        $isSettingUp2FA = $user->two_factor_secret !== null && !$is2FAEnabled;
        $recoveryCodes = $is2FAEnabled ? json_decode(decrypt($user->two_factor_recovery_codes ?? '[]'), true) ?? [] : [];

        return view('livewire.profile.two-factor', [
            'user' => $user,
            'is2FAEnabled' => $is2FAEnabled,
            'isSettingUp2FA' => $isSettingUp2FA,
            'recoveryCodes' => $recoveryCodes,
        ])->layout('layouts.app')->title('Security Settings');
    }
}