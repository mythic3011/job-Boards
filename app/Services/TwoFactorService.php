<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;

class TwoFactorService
{
    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
        private readonly EnableTwoFactorAuthentication $enableAction,
        private readonly ConfirmTwoFactorAuthentication $confirmAction,
        private readonly DisableTwoFactorAuthentication $disableAction,
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Check if 2FA is enabled for the user.
     */
    public function isEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null;
    }

    /**
     * Check if 2FA setup is in progress (enabled but not confirmed).
     */
    public function isSetupInProgress(User $user): bool
    {
        return $user->two_factor_secret !== null && !$this->isEnabled($user);
    }

    /**
     * Verify a two-factor authentication code.
     */
    public function verifyCode(User $user, string $code): bool
    {
        if (empty($code) || !$user->two_factor_secret) {
            return false;
        }

        return $this->verifyTotp($user, $code);
    }

    /**
     * Raw TOTP verification core.
     */
    private function verifyTotp(User $user, string $code): bool
    {
        try {
            $secret = decrypt($user->two_factor_secret);
            return $this->provider->verify($secret, $code);
        } catch (\Exception $e) {
            \Log::error('2FA verification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Enable two-factor authentication for the user.
     * This generates the secret and recovery codes but doesn't confirm it yet.
     */
    public function enable(User $user): void
    {
        if (!$user->two_factor_secret) {
            ($this->enableAction)($user);

            $this->auditLogger->logBusinessEvent(
                eventType: '2fa.enabled',
                request: request(),
                targetType: 'user',
                targetIdcode: $user->idcode,
                meta: [
                    'user_id' => $user->id,
                    'status' => 'pending_confirmation',
                ]
            );
        }
    }

    /**
     * Confirm two-factor authentication with a verification code.
     * Let Fortify's action handle the verification to avoid double-verification issues.
     */
    public function confirm(User $user, string $code): bool
    {
        if (empty($code) || !$user->two_factor_secret) {
            return false;
        }

        try {
            ($this->confirmAction)($user, $code);
        } catch (\Exception $e) {
            \Log::error('2FA confirmation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        $this->auditLogger->logBusinessEvent(
            eventType: '2fa.confirmed',
            request: request(),
            targetType: 'user',
            targetIdcode: $user->idcode,
            meta: [
                'user_id' => $user->id,
                'confirmed_at' => now()->toDateTimeString(),
                'ip' => request()->ip(),
            ]
        );

        return true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(User $user): void
    {
        if ($this->isEnabled($user)) {
            ($this->disableAction)($user);

            $this->auditLogger->logBusinessEvent(
                eventType: '2fa.disabled',
                request: request(),
                targetType: 'user',
                targetIdcode: $user->idcode,
                meta: [
                    'user_id' => $user->id,
                    'disabled_at' => now()->toDateTimeString(),
                ]
            );
        }
    }

    /**
     * Cancel 2FA setup in progress.
     */
    public function cancelSetup(User $user): void
    {
        if ($this->isSetupInProgress($user)) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
            ])->save();

            $this->auditLogger->logBusinessEvent(
                eventType: '2fa.setup_cancelled',
                request: request(),
                targetType: 'user',
                targetIdcode: $user->idcode,
                meta: ['user_id' => $user->id]
            );
        }
    }

    /**
     * Regenerate recovery codes for the user.
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        if (!$this->isEnabled($user)) {
            throw new \RuntimeException('Two-factor authentication must be enabled to regenerate recovery codes.');
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        $this->auditLogger->logBusinessEvent(
            eventType: '2fa.recovery_codes_regenerated',
            request: request(),
            targetType: 'user',
            targetIdcode: $user->idcode,
            meta: [
                'user_id' => $user->id,
                'regenerated_at' => now()->toDateTimeString(),
            ]
        );

        return $recoveryCodes;
    }

    /**
     * Get recovery codes for the user.
     * Recovery codes are stored as encrypted JSON strings.
     */
    public function getRecoveryCodes(User $user): array
    {
        if (!$this->isEnabled($user) || !$user->two_factor_recovery_codes) {
            return [];
        }

        try {
            // Fortify stores recovery codes as encrypted JSON
            $decrypted = decrypt($user->two_factor_recovery_codes);
            $decoded = json_decode($decrypted, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt recovery codes', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Generate new recovery codes using Laravel Fortify's format.
     */
    private function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn() => RecoveryCode::generate())
            ->toArray();
    }

    /**
     * Get QR code SVG for the user.
     */
    public function getQrCodeSvg(User $user): ?string
    {
        if (!$user->two_factor_secret) {
            return null;
        }

        try {
            return $user->twoFactorQrCodeSvg();
        } catch (\Exception $e) {
            \Log::error('Failed to generate QR code', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get the decrypted 2FA secret for manual entry.
     */
    public function getSecret(User $user): ?string
    {
        if (!$user->two_factor_secret) {
            return null;
        }

        try {
            // Decrypt the secret that Fortify stores encrypted
            return decrypt($user->two_factor_secret);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt 2FA secret', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify 2FA code and throw validation exception if invalid.
     */
    public function verifyCodeOrFail(User $user, string $code): void
    {
        if (empty($code)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'two_factor_code' => ['Two-factor authentication code is required.'],
            ]);
        }

        if (!$this->verifyCode($user, $code)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'two_factor_code' => ['The provided two-factor authentication code is invalid.'],
            ]);
        }
    }
}
