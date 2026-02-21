<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SendPasswordResetLinkWithTwoFactor
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TwoFactorService $twoFactorService,
    ) {}

    /**
     * Send a password reset link with 2FA verification.
     */
    public function __invoke(array $input): array
    {
        // Rate limiting
        $key = 'password-reset-2fa:' . $input['email'];
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => ["Too many attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        // Find user by email
        $user = User::where('email', $input['email'])->first();

        if (!$user) {
            // Don't reveal if user exists
            RateLimiter::hit($key, 300); // 5 minutes
            throw ValidationException::withMessages([
                'email' => ['We could not find a user with that email address.'],
            ]);
        }

        // Check if user has 2FA enabled
        if (!$user->two_factor_secret) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages([
                'email' => ['Two-factor authentication is not enabled for this account. Please contact support.'],
            ]);
        }

        // Verify 2FA code or recovery code
        $verified = false;

        if (!empty($input['code'])) {
            // Verify OTP code
            $verified = $this->twoFactorService->verifyCode($user, $input['code']);
            
            if (!$verified) {
                RateLimiter::hit($key, 60);
                
                $this->auditLogger->logSecurityEvent(
                    eventType: 'password_reset.invalid_2fa_code',
                    request: request(),
                    userId: $user->id,
                    meta: [
                        'email' => $user->email,
                        'ip' => request()->ip(),
                    ]
                );
                
                throw ValidationException::withMessages([
                    'code' => ['The authentication code is invalid.'],
                ]);
            }
        } elseif (!empty($input['recovery_code'])) {
            // Verify recovery code via TwoFactorService
            $inputCode = str_replace('-', '', $input['recovery_code']);
            $recoveryCodes = $this->twoFactorService->getRecoveryCodes($user);
            foreach ($recoveryCodes as $recoveryCode) {
                if (hash_equals(str_replace('-', '', $recoveryCode), $inputCode)) {
                    $verified = true;
                    break;
                }
            }
            
            if (!$verified) {
                RateLimiter::hit($key, 60);
                
                $this->auditLogger->logSecurityEvent(
                    eventType: 'password_reset.invalid_recovery_code',
                    request: request(),
                    userId: $user->id,
                    meta: [
                        'email' => $user->email,
                        'ip' => request()->ip(),
                    ]
                );
                
                throw ValidationException::withMessages([
                    'recovery_code' => ['The recovery code is invalid.'],
                ]);
            }
        } else {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'code' => ['Please provide either an authentication code or a recovery code.'],
            ]);
        }

        // 2FA verified, send password reset link (or log token locally for debugging)
        if (app()->environment('local')) {
            $token = Password::broker()->createToken($user);
            RateLimiter::clear($key);

            // Log token for debugging ONLY - never expose in response
            \Log::debug('Password reset token generated (LOCAL ONLY)', [
                'email' => $user->email,
                'reset_url' => route('password.reset', [
                    'token' => $token,
                    'email' => $user->email
                ]),
            ]);

            $this->auditLogger->logSecurityEvent(
                eventType: 'password_reset.link_generated',
                request: request(),
                userId: $user->id,
                meta: [
                    'email' => $user->email,
                    'ip' => request()->ip(),
                    'verified_with' => !empty($input['code']) ? '2fa_code' : 'recovery_code',
                    'local_shortcut' => true,
                ]
            );

            return [
                'status' => Password::RESET_LINK_SENT,
                'local' => true,
            ];
        }

        $status = Password::sendResetLink(['email' => $input['email']]);

        if ($status === Password::RESET_LINK_SENT) {
            RateLimiter::clear($key);

            $this->auditLogger->logSecurityEvent(
                eventType: 'password_reset.link_sent',
                request: request(),
                userId: $user->id,
                meta: [
                    'email' => $user->email,
                    'ip' => request()->ip(),
                    'verified_with' => !empty($input['code']) ? '2fa_code' : 'recovery_code',
                ]
            );

            return [
                'status' => $status,
                'local' => false,
            ];
        }

        RateLimiter::hit($key, 60);
        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

}
