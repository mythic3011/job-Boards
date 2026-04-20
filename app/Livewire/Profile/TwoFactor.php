<?php

namespace App\Livewire\Profile;

use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Js;
use Livewire\Component;

class TwoFactor extends Component
{
    public string $verificationCode = '';

    public bool $codeIsValid = false;

    public function enable2FA(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        app(TwoFactorService::class)->enable($user);
        $user->refresh();
    }

    public function updatedVerificationCode(string $value): void
    {
        $value = trim($value);
        $this->codeIsValid = false;
        $this->resetErrorBag();

        if (strlen($value) !== 6 || ! preg_match('/^\d{6}$/', $value)) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $key = '2fa-verify:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('verificationCode', "Too many attempts. Try again in {$seconds} seconds.");

            return;
        }

        RateLimiter::hit($key, 60);

        $service = app(TwoFactorService::class);

        if (! $service->verifyCode($user, $value)) {
            $this->addError('verificationCode', 'The verification code is incorrect. Please try again.');

            return;
        }

        RateLimiter::clear($key);

        if (! $service->confirm($user, $value)) {
            $this->addError('verificationCode', 'We could not finalise two-factor setup. Please try again.');

            return;
        }

        $this->codeIsValid = true;
        session()->flash('success', 'Two-factor authentication is now active! Your account is more secure.');
        $redirectTo = session()->pull('registration.pending_intended', route('profile.show'));
        session()->forget('url.intended');
        $this->js('setTimeout(() => window.location.href = '.Js::from($redirectTo).', 800);');
    }

    public function disable2FA(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        app(TwoFactorService::class)->disable($user);
        session()->flash('success', 'Two-factor authentication has been disabled.');
    }

    public function cancel2FA(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        app(TwoFactorService::class)->cancelSetup($user);
        $this->verificationCode = '';
    }

    public function regenerateRecoveryCodes(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        app(TwoFactorService::class)->regenerateRecoveryCodes($user);
        session()->flash('success', 'New recovery codes have been generated.');
    }

    public function render()
    {
        $user = Auth::user()?->fresh();

        if (! $user) {
            return view('livewire.profile.two-factor', [
                'user' => null,
                'is2FAEnabled' => false,
                'isSettingUp2FA' => false,
                'recoveryCodes' => [],
            ])->layout('layouts.app')->title('Security Settings');
        }

        $twoFactorService = app(TwoFactorService::class);
        $isSettingUp2FA = $twoFactorService->isSetupInProgress($user);

        return view('livewire.profile.two-factor', [
            'user' => $user,
            'is2FAEnabled' => $twoFactorService->isEnabled($user),
            'isSettingUp2FA' => $isSettingUp2FA,
            'recoveryCodes' => $twoFactorService->getRecoveryCodes($user),
            'secret' => $isSettingUp2FA ? $twoFactorService->getSecret($user) : null,
        ])->layout('layouts.app')->title('Security Settings');
    }
}
