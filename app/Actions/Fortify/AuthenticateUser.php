<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AuthenticateUser
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * Attempt to authenticate the user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Models\User|null
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function __invoke(Request $request): ?User
    {
        $username = $request->input(Fortify::username());
        $password = $request->input('password');

        // Find user by login_id or email
        $user = User::where('login_id', $username)
            ->orWhere('email', $username)
            ->first();

        // Check if account is locked (prevent user enumeration)
        if ($user && $user->isLocked()) {
            $this->handleLockedAccount($user, $request, $username);
            throw ValidationException::withMessages([
                Fortify::username() => [__('Your account has been temporarily locked. Please try again later.')],
            ]);
        }

        // Verify credentials
        if (!$user || !Hash::check($password, $user->password)) {
            $this->handleFailedAuthentication($user, $request, $username);
            throw ValidationException::withMessages([
                Fortify::username() => [__('These credentials do not match our records.')],
            ]);
        }

        // Successful login - reset lock if exists
        if ($user->locked_until) {
            $user->update(['locked_until' => null]);
        }

        // OWASP A07: Regenerate session to prevent session fixation
        // Also invalidate old session completely
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->regenerate();

        // Log successful login
        Log::info('User logged in', [
            'user_id' => $user->id,
            'username' => $user->login_id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $user;
    }

    /**
     * Handle locked account attempt.
     */
    private function handleLockedAccount(User $user, Request $request, string $username): void
    {
        $this->auditLogger->logRequestEvent(
            eventType: 'account_locked',
            request: $request,
            statusCode: 423,
            targetType: 'user',
            targetIdcode: $user->idcode,
            meta: [
                'username' => $username,
                'locked_until' => $user->locked_until?->toDateTimeString(),
            ]
        );

        Log::warning('Login attempt on locked account', [
            'username' => $username,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Handle failed authentication attempt.
     */
    private function handleFailedAuthentication(?User $user, Request $request, string $username): void
    {
        // Track failed login attempts
        if ($user) {
            $this->handleFailedLoginAttempt($user, $request);
        }

        // Audit log failed login (OWASP A09)
        $this->auditLogger->logRequestEvent(
            eventType: 'login_failed',
            request: $request,
            statusCode: 422,
            targetType: $user ? 'user' : null,
            targetIdcode: $user?->idcode,
            meta: [
                'username' => $username,
                'reason' => $user ? 'invalid_password' : 'user_not_found',
            ]
        );

        Log::warning('Failed login attempt', [
            'username' => $username,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Handle failed login attempt and lock account if necessary.
     */
    private function handleFailedLoginAttempt(User $user, Request $request): void
    {
        // Get failed attempts count from cache (per username/email + IP)
        $key = 'login_attempts:' . ($user->login_id ?? $user->email) . ':' . $request->ip();
        $attempts = cache()->get($key, 0) + 1;

        // Store attempts for 30 minutes
        cache()->put($key, $attempts, now()->addMinutes(30));

        // Lock account after 5 failed attempts
        if ($attempts >= 5) {
            $user->update([
                'locked_until' => now()->addMinutes(30),
            ]);

            // Refresh to get updated locked_until
            $user->refresh();

            // Audit log account lock
            $this->auditLogger->logBusinessEvent(
                eventType: 'account_locked',
                request: $request,
                targetType: 'user',
                targetIdcode: $user->idcode,
                meta: [
                    'reason' => 'failed_login_attempts',
                    'attempts' => $attempts,
                    'locked_until' => $user->locked_until->toDateTimeString(),
                ]
            );

            Log::warning('Account locked due to failed login attempts', [
                'user_id' => $user->id,
                'username' => $user->login_id,
                'email' => $user->email,
                'attempts' => $attempts,
                'ip' => $request->ip(),
                'locked_until' => $user->locked_until,
            ]);
        }
    }
}
