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

    public bool $showDisableConfirmation = false;

    public bool $showRegenerateConfirmation = false;

    public string $verificationState = 'idle';

    public ?string $verificationFeedback = null;

    public function enable2FA(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        app(TwoFactorService::class)->enable($user);
        $this->verificationCode = '';
        $this->codeIsValid = false;
        $this->verificationState = 'idle';
        $this->verificationFeedback = 'Step 2: Enter the latest 6-digit code from your authenticator app.';
        $user->refresh();
    }

    public function updatedVerificationCode(string $value): void
    {
        $value = preg_replace('/\D+/', '', trim($value));
        $this->verificationCode = substr($value ?? '', 0, 6);
        $this->codeIsValid = false;
        $this->resetErrorBag('verificationCode');

        if ($this->verificationCode === '') {
            $this->verificationState = 'idle';
            $this->verificationFeedback = 'Step 2: Enter a 6-digit code from your authenticator app.';

            return;
        }

        if (strlen($this->verificationCode) < 6) {
            $this->verificationState = 'incomplete';
            $this->verificationFeedback = 'Keep typing until all 6 digits are filled.';

            return;
        }

        $this->verificationState = 'ready';
        $this->verificationFeedback = 'Step 3: Click "Verify and enable 2FA" to finish setup.';
    }

    public function verifyCode(): void
    {
        $this->codeIsValid = false;
        $this->resetErrorBag('verificationCode');

        $value = trim($this->verificationCode);

        if (! preg_match('/^\d{6}$/', $value)) {
            $this->verificationState = 'incomplete';
            $this->verificationFeedback = 'Enter a valid 6-digit code before verifying.';

            return;
        }

        $this->verificationState = 'checking';
        $this->verificationFeedback = 'Verifying your code...';

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $key = '2fa-verify:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('verificationCode', "Too many attempts. Try again in {$seconds} seconds.");
            $this->verificationState = 'error';
            $this->verificationFeedback = "Too many attempts. Wait {$seconds} seconds, then try the newest code.";

            return;
        }

        RateLimiter::hit($key, 60);

        $service = app(TwoFactorService::class);

        if (! $service->confirm($user, $value)) {
            $this->addError('verificationCode', 'Invalid or expired code. Check your app and try the newest 6-digit code.');
            $this->verificationState = 'error';
            $this->verificationFeedback = 'Invalid or expired code. Wait for the next code and enter it again.';

            return;
        }

        RateLimiter::clear($key);
        $this->codeIsValid = true;
        $this->verificationState = 'success';
        $this->verificationFeedback = 'Success. Two-factor authentication is enabled. Redirecting...';
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

        if (! $this->showDisableConfirmation) {
            $this->showDisableConfirmation = true;

            return;
        }

        app(TwoFactorService::class)->disable($user);
        $this->showDisableConfirmation = false;
        session()->flash('success', 'Two-factor authentication is now off. You can turn it back on anytime from this page.');
    }

    public function promptDisable2FA(): void
    {
        $this->showDisableConfirmation = true;
    }

    public function cancelDisable2FA(): void
    {
        $this->showDisableConfirmation = false;
    }

    public function cancel2FA(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        app(TwoFactorService::class)->cancelSetup($user);
        $this->verificationCode = '';
        $this->codeIsValid = false;
        $this->verificationState = 'idle';
        $this->verificationFeedback = 'Setup cancelled. You can restart 2FA setup anytime.';
    }

    public function regenerateRecoveryCodes(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        if (! $this->showRegenerateConfirmation) {
            $this->showRegenerateConfirmation = true;

            return;
        }

        app(TwoFactorService::class)->regenerateRecoveryCodes($user);
        $this->showRegenerateConfirmation = false;
        session()->flash('success', 'New recovery codes are ready. Store them in a safe place and replace your old copy.');
    }

    public function promptRegenerateRecoveryCodes(): void
    {
        $this->showRegenerateConfirmation = true;
    }

    public function cancelRegenerateRecoveryCodes(): void
    {
        $this->showRegenerateConfirmation = false;
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
