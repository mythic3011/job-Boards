<?php

namespace App\Services;

use App\Http\Middleware\HandleSuspiciousUserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InstallCompletionCoordinator
{
    private const REQUEST_TOLERANCE = 60;

    private const MAX_INSTALL_ATTEMPTS = 5;

    private const INSTALL_ATTEMPT_CACHE_TTL = 10;

    private const INSTALL_MAX_ATTEMPTS_DURING_SESSION = 20;

    private const DEFAULT_RECOVERY_CODE_COUNT = 8;

    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly HandleSuspiciousUserAgent $suspiciousUserAgent
    ) {}

    /**
     * Canonical admin password policy used by install and admin-create entry points.
     *
     * @return list<string>
     */
    public function passwordRules(bool $withConfirmation = false): array
    {
        $rules = [
            'required',
            'string',
            'min:12',
            'max:255',
            'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
        ];

        if ($withConfirmation) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    /**
     * Canonical install completion request validation rules.
     *
     * @return array<string, mixed>
     */
    public function completionValidationRules(bool $requireReplayFields): array
    {
        $rules = [
            'admin_username' => 'nullable|string|min:3|max:255|regex:/^[a-zA-Z0-9_]+$/',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email:rfc|unique:users,email|max:255',
            'admin_password' => $this->passwordRules(),
            'admin_password_confirmation' => 'required|string|same:admin_password',
            'two_factor_secret' => 'required|string|regex:/^[A-Z2-7]{16,}$/i',
            'otp_code' => 'required|string|size:6',
            'install_demo_data' => 'nullable|boolean',
            'demo' => 'nullable|boolean',
        ];

        if ($requireReplayFields) {
            $rules['timestamp'] = 'required|integer';
            $rules['session'] = 'required|string|max:100';
        }

        return $rules;
    }

    public function validateRequestAge(Request $request, int $maxAge): void
    {
        $requestAge = now()->timestamp - (int) $request->input('timestamp', 0);
        if ($requestAge > $maxAge || $requestAge < -self::REQUEST_TOLERANCE) {
            throw ValidationException::withMessages([
                'timestamp' => 'Request expired',
            ]);
        }
    }

    public function enforceCompletionChecks(Request $request, bool $checkReplayAndRateLimit, bool $isActiveInstallSession, int $maxAge): void
    {
        if ($checkReplayAndRateLimit) {
            $this->validateRequestAge($request, $maxAge);
        }

        $this->verifyOtp($request);
        $this->checkSuspiciousActivity($request);

        if (! $checkReplayAndRateLimit) {
            return;
        }

        if ($isActiveInstallSession) {
            $this->checkRateLimitDuringInstall($request);
        } else {
            $this->checkRateLimit($request);
        }
    }

    public function clearRateLimit(Request $request): void
    {
        Cache::forget($this->getRateLimitKey($request));
    }

    public function generateProvisioningSecret(): string
    {
        return $this->twoFactorService->generateSetupSecret();
    }

    /**
     * @return array<int, string>
     */
    public function generateRecoveryCodes(): array
    {
        return $this->twoFactorService->generateRecoveryCodes(self::DEFAULT_RECOVERY_CODE_COUNT);
    }

    public function generateQrCodeInline(string $appName, string $email, string $secret): string
    {
        return $this->twoFactorService->generateQrCodeInline($appName, $email, $secret);
    }

    public function verifyProvisioningCode(string $secret, string $code): bool
    {
        return $this->twoFactorService->verifyProvisioningCode($secret, $code);
    }

    private function verifyOtp(Request $request): void
    {
        $secret = $request->string('two_factor_secret')->trim()->value();
        $code = $request->string('otp_code')->trim()->value();

        if ($secret === '' || $code === '') {
            throw ValidationException::withMessages([
                'otp_code' => ['OTP verification is required.'],
            ]);
        }

        if (! $this->verifyProvisioningCode($secret, $code)) {
            throw ValidationException::withMessages([
                'otp_code' => ['The verification code is invalid. Please check your authenticator app.'],
            ]);
        }
    }

    private function checkSuspiciousActivity(Request $request): void
    {
        if (! $this->suspiciousUserAgent->isSuspicious($request)) {
            return;
        }

        Log::warning('Suspicious install attempt blocked', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session' => $request->session()->getId(),
        ]);

        abort(403, 'Access denied');
    }

    private function checkRateLimit(Request $request): void
    {
        $key = $this->getRateLimitKey($request);
        $attempts = Cache::get($key, 0);

        if ($attempts >= self::MAX_INSTALL_ATTEMPTS) {
            abort(429, 'Too many attempts. Try again later.');
        }

        Cache::put($key, $attempts + 1, now()->addMinutes(self::INSTALL_ATTEMPT_CACHE_TTL));
    }

    private function checkRateLimitDuringInstall(Request $request): void
    {
        $key = $this->getRateLimitKey($request).'_install';
        $attempts = Cache::get($key, 0);

        if ($attempts >= self::INSTALL_MAX_ATTEMPTS_DURING_SESSION) {
            Log::warning('Install rate limit exceeded during active session', [
                'ip' => $request->ip(),
                'session' => $request->session()->getId(),
                'attempts' => $attempts,
            ]);
            abort(429, 'Too many attempts during installation. Please wait a moment.');
        }

        Cache::put($key, $attempts + 1, now()->addMinutes(5));
    }

    private function getRateLimitKey(Request $request): string
    {
        return 'install_attempts_'.$request->ip();
    }
}
