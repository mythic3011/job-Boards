<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function reset(User $user, array $input): void
    {
        if (!$user->two_factor_confirmed_at) {
            throw \Illuminate\Validation\ValidationException::withMessages([
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

        $provider = app(TwoFactorAuthenticationProvider::class);
        
        if (!$provider->verify(
            decrypt($user->two_factor_secret),
            $input['two_factor_code']
        )) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'two_factor_code' => __('The provided two-factor authentication code is invalid.'),
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
