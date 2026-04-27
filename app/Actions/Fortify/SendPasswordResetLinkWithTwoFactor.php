<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
        $identifier = trim((string) ($input['login'] ?? $input['email'] ?? ''));
        if ($identifier === '') {
            throw ValidationException::withMessages([
                'login' => ['Please enter your username or email address.'],
            ]);
        }

        // Rate limiting
        $key = 'password-reset-2fa:'.Str::transliterate(Str::lower($identifier));
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'login' => ["Too many attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        // Find user by login_id or email
        $user = User::query()
            ->where('login_id', $identifier)
            ->orWhere('email', Str::lower($identifier))
            ->first();

        if (! $user) {
            // Don't reveal if user exists
            RateLimiter::hit($key, 300); // 5 minutes
            return [
                'status' => Password::RESET_LINK_SENT,
                'local' => app()->environment('local'),
            ];
        }

        if (! $this->twoFactorService->isEnabled($user)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages([
                'login' => ['This account cannot reset password here because two-factor authentication is not enabled.'],
            ]);
        }

        $verifiedWith = '2fa_code';
        if (empty($input['code']) && empty($input['recovery_code'])) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor code is required.'],
            ]);
        }

        if (! empty($input['code']) || ! empty($input['recovery_code'])) {
            if (! empty($input['code'])) {
                $verified = $this->twoFactorService->verifyCode($user, (string) $input['code']);
                if (! $verified) {
                    RateLimiter::hit($key, 60);
                    $this->auditLogger->logSecurityEvent(
                        eventType: 'password_reset.invalid_2fa_code',
                        request: request(),
                        userId: $user->id,
                        meta: ['ip' => request()->ip()]
                    );
                    throw ValidationException::withMessages([
                        'code' => ['The authentication code is invalid.'],
                    ]);
                }
                $verifiedWith = '2fa_code';
            } else {
                $verified = $this->twoFactorService->verifyAndConsumeRecoveryCode($user, (string) $input['recovery_code']);
                if (! $verified) {
                    RateLimiter::hit($key, 60);
                    $this->auditLogger->logSecurityEvent(
                        eventType: 'password_reset.invalid_recovery_code',
                        request: request(),
                        userId: $user->id,
                        meta: ['ip' => request()->ip()]
                    );
                    throw ValidationException::withMessages([
                        'recovery_code' => ['The recovery code is invalid.'],
                    ]);
                }
                $verifiedWith = 'recovery_code';
            }
        }

        // In local development we still avoid returning raw reset tokens to the caller.
        if (app()->environment('local')) {
            $token = Password::broker()->createToken($user);
            RateLimiter::clear($key);

            Log::debug('Password reset token generated (LOCAL ONLY)', [
                'token_generated' => true,
                'user_id' => $user->id,
            ]);

            $this->auditLogger->logSecurityEvent(
                eventType: 'password_reset.link_generated',
                    request: request(),
                    userId: $user->id,
                    meta: [
                        'ip' => request()->ip(),
                        'verified_with' => $verifiedWith,
                    ]
                );

            return [
                'status' => Password::RESET_LINK_SENT,
                'local' => true,
            ];
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            RateLimiter::clear($key);

            $this->auditLogger->logSecurityEvent(
                eventType: 'password_reset.link_sent',
                request: request(),
                userId: $user->id,
                meta: [
                    'ip' => request()->ip(),
                    'verified_with' => $verifiedWith,
                ]
            );

            return [
                'status' => $status,
                'local' => false,
            ];
        }

        RateLimiter::hit($key, 60);
        throw ValidationException::withMessages([
            'login' => [__($status)],
        ]);
    }
}
