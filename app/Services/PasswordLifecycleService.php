<?php

namespace App\Services;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class PasswordLifecycleService
{
    use PasswordValidationRules;

    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly TwoFactorAuthenticationProvider $twoFactorProvider
    ) {}

    public function updateViaFortify(User $user, array $input): void
    {
        if (! $this->twoFactorService->isEnabled($user)) {
            throw ValidationException::withMessages([
                'password' => __('Two-factor authentication must be enabled before you can change your password.'),
            ])->errorBag('updatePassword');
        }

        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
            'two_factor_code' => ['required', 'string', 'size:6'],
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
            'two_factor_code.required' => __('The two-factor authentication code is required.'),
            'two_factor_code.size' => __('The two-factor authentication code must be 6 digits.'),
        ])->validateWithBag('updatePassword');

        if (! $this->verifyTwoFactorCode($user, (string) ($input['two_factor_code'] ?? ''))) {
            throw ValidationException::withMessages([
                'two_factor_code' => __('The provided two-factor authentication code is invalid.'),
            ])->errorBag('updatePassword');
        }

        $this->setPassword($user, (string) $input['password']);
    }

    public function resetViaFortify(User $user, array $input): void
    {
        if (! $this->twoFactorService->isEnabled($user)) {
            throw ValidationException::withMessages([
                'password' => __('Two-factor authentication must be enabled before you can reset your password.'),
            ]);
        }

        Validator::make($input, [
            'password' => $this->passwordRules(),
            'two_factor_code' => ['required', 'string', 'size:6'],
        ], [
            'two_factor_code.required' => __('The two-factor authentication code is required.'),
            'two_factor_code.size' => __('The two-factor authentication code must be 6 digits.'),
        ])->validate();

        if (! $this->verifyTwoFactorCode($user, (string) ($input['two_factor_code'] ?? ''))) {
            throw ValidationException::withMessages([
                'two_factor_code' => __('The provided two-factor authentication code is invalid.'),
            ]);
        }

        $this->setPassword($user, (string) $input['password']);
    }

    public function updateViaProfile(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(12)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(3),
            ],
            'two_factor_code' => $this->twoFactorService->isEnabled($user)
                ? ['required', 'string', 'size:6']
                : ['nullable', 'string'],
        ], [
            'two_factor_code.required' => 'Two-factor authentication code is required.',
            'two_factor_code.size' => 'Two-factor authentication code must be 6 digits.',
        ])->validate();

        if (! Hash::check((string) $input['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        if ($this->twoFactorService->isEnabled($user)) {
            $this->twoFactorService->verifyCodeOrFail($user, (string) ($input['two_factor_code'] ?? ''));
        }

        $this->setPassword($user, (string) $input['password']);
    }

    public function assertResetAllowed(User $user, ?string $twoFactorCode, bool $adminInitiated): void
    {
        if ($adminInitiated || ! $this->twoFactorService->isEnabled($user)) {
            return;
        }

        Validator::make([
            'two_factor_code' => $twoFactorCode,
        ], [
            'two_factor_code' => ['required', 'string', 'size:6'],
        ])->validate();

        if (! $this->twoFactorService->verifyCode($user, (string) $twoFactorCode)) {
            throw ValidationException::withMessages([
                'two_factor_code' => 'The provided two-factor authentication code is invalid.',
            ]);
        }
    }

    public function setPassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();
    }

    private function verifyTwoFactorCode(User $user, string $code): bool
    {
        if ($code === '' || ! $user->two_factor_secret) {
            return false;
        }

        try {
            return $this->twoFactorProvider->verify(
                decrypt($user->two_factor_secret),
                $code
            );
        } catch (\Throwable) {
            return false;
        }
    }
}
