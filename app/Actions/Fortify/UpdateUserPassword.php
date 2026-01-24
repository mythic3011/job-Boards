<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function update(User $user, array $input): void
    {
        if (!$user->two_factor_confirmed_at) {
            throw \Illuminate\Validation\ValidationException::withMessages([
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

        $provider = app(TwoFactorAuthenticationProvider::class);
        
        if (!$provider->verify(
            decrypt($user->two_factor_secret),
            $input['two_factor_code']
        )) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'two_factor_code' => __('The provided two-factor authentication code is invalid.'),
            ])->errorBag('updatePassword');
        }

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
