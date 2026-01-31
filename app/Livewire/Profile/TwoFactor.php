<?php

namespace App\Livewire\Profile;

use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
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

    public function __construct()
    {
        parent::__construct();
    }

    public function enable2FA(TwoFactorService $twoFactorService)
    {
        $user = Auth::user();
        $twoFactorService->enable($user);
        $this->dispatch('2fa-enabled');
    }

    public function confirm2FA(TwoFactorService $twoFactorService)
    {
        $this->validate();

        $user = Auth::user();

        if (!$twoFactorService->confirm($user, $this->verificationCode)) {
            $this->addError('verificationCode', 'The verification code is incorrect. Please try again.');
            return;
        }

        session()->flash('success', '🎉 Two-factor authentication is now active! Your account is more secure.');
        $this->verificationCode = '';
    }

    public function disable2FA(TwoFactorService $twoFactorService)
    {
        $twoFactorService->disable(Auth::user());
        session()->flash('success', 'Two-factor authentication has been disabled.');
    }

    public function cancel2FA(TwoFactorService $twoFactorService)
    {
        $twoFactorService->cancelSetup(Auth::user());
        $this->verificationCode = '';
    }

    public function regenerateRecoveryCodes(TwoFactorService $twoFactorService)
    {
        $twoFactorService->regenerateRecoveryCodes(Auth::user());
        session()->flash('success', 'New recovery codes have been generated.');
    }

    public function render(TwoFactorService $twoFactorService)
    {
        $user = Auth::user();
        $is2FAEnabled = $twoFactorService->isEnabled($user);
        $isSettingUp2FA = $twoFactorService->isSetupInProgress($user);
        $recoveryCodes = $twoFactorService->getRecoveryCodes($user);

        return view('livewire.profile.two-factor', [
            'user' => $user,
            'is2FAEnabled' => $is2FAEnabled,
            'isSettingUp2FA' => $isSettingUp2FA,
            'recoveryCodes' => $recoveryCodes,
        ])->layout('layouts.app')->title('Security Settings');
    }
}