<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * Authenticate user with enhanced security features.
     */
    public function authenticate(Request $request, string $username, string $password): ?User
    {
        $user = $this->findUserByCredentials($username);

        // Check account lock status
        if ($user && $user->isLocked()) {
            $this->handleLockedAccount($user, $request, $username);
            $this->throwLockoutException($user);
        }

        // Verify credentials
        if (!$user || !Hash::check($password, $user->password)) {
            $this->handleFailedAuthentication($user, $request, $username);
            throw ValidationException::withMessages([
                'login_id' => ['These credentials do not match our records.'],
            ]);
        }

        // Successful authentication
        $this->handleSuccessfulAuthentication($user, $request);
        
        return $user;
    }

    /**
     * Find user by login_id or email.
     */
    private function findUserByCredentials(string $username): ?User
    {
        return User::where('login_id', $username)
            ->orWhere('email', $username)
            ->first();
    }

    /**
     * Handle successful authentication.
     */
    private function handleSuccessfulAuthentication(User $user, Request $request): void
    {
        // Clear any existing lockout
        if ($user->locked_until) {
            $user->update(['locked_until' => null]);
        }

        // Clear failed attempts cache
        $this->clearFailedAttempts($user, $request);

        // Regenerate session for security
        $this->regenerateSession($request);

        // Log successful login
        Log::info('User authenticated successfully', [
            'user_id' => $user->id,
            'username' => $user->login_id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->auditLogger->logBusinessEvent(
            eventType: 'audit.auth.verify.success',
            request: $request,
            targetType: 'user',
            targetIdcode: $user->idcode,
            meta: [
                'username' => $user->login_id,
                'email' => $user->email,
            ],
            actorUserId: $user->id,
            actorType: 'user',
        );
    }

    /**
     * Handle failed authentication attempt.
     */
    private function handleFailedAuthentication(?User $user, Request $request, string $username): void
    {
        $auditUsername = $this->trimAuditUsername($username);

        if ($user) {
            $this->trackFailedAttempt($user, $request);
        }

        $targetType = $user ? 'user' : 'login_identifier';
        $targetIdcode = $user?->idcode ?? $this->canonicalUnknownLoginIdentifier($auditUsername);

        // Audit log failed login
        $this->auditLogger->logRequestEvent(
            eventType: 'audit.auth.verify.denied',
            request: $request,
            statusCode: 422,
            targetType: $targetType,
            targetIdcode: $targetIdcode,
            meta: [
                'username' => $auditUsername,
                'reason' => $user ? 'invalid_password' : 'user_not_found',
            ],
            actorUserId: $user?->id,
            actorType: $user ? 'user' : 'guest',
        );

        Log::warning('Authentication failed', [
            'username' => $auditUsername,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'reason' => $user ? 'invalid_password' : 'user_not_found',
        ]);
    }

    /**
     * Handle locked account attempt.
     */
    private function handleLockedAccount(User $user, Request $request, string $username): void
    {
        $this->auditLogger->logRequestEvent(
            eventType: 'audit.auth.verify.denied',
            request: $request,
            statusCode: 422,
            targetType: 'user',
            targetIdcode: $user->idcode,
            meta: [
                'username' => $username,
                'reason' => 'account_locked',
                'locked_until' => $user->locked_until?->toDateTimeString(),
            ],
            actorUserId: $user->id,
            actorType: 'user',
        );

        Log::warning('Login attempt on locked account', [
            'username' => $username,
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'locked_until' => $user->locked_until,
        ]);
    }

    /**
     * Track failed login attempts and lock account if necessary.
     */
    private function trackFailedAttempt(User $user, Request $request): void
    {
        $cacheKey = $this->getFailedAttemptsKey($user, $request);
        $attempts = Cache::get($cacheKey, 0) + 1;
        $maxAttempts = (int) config('auth.max_login_attempts', 5);
        $lockoutMinutes = (int) config('auth.lockout_minutes', 30);

        // Store attempts for lockout duration
        Cache::put($cacheKey, $attempts, now()->addMinutes($lockoutMinutes));

        if ($attempts >= $maxAttempts) {
            $this->lockAccount($user, $request, $attempts, $lockoutMinutes);
        } elseif ($attempts >= 2) {
            $this->setProgressiveWarning($attempts, $maxAttempts);
        }
    }

    /**
     * Lock user account after max failed attempts.
     */
    private function lockAccount(User $user, Request $request, int $attempts, int $lockoutMinutes): void
    {
        $lockedUntil = now()->addMinutes($lockoutMinutes);
        $user->update(['locked_until' => $lockedUntil]);

        $this->auditLogger->logRequestEvent(
            eventType: 'audit.auth.locked',
            request: $request,
            statusCode: 422,
            targetType: 'user',
            targetIdcode: $user->idcode,
            meta: [
                'reason' => 'failed_login_attempts',
                'attempts' => $attempts,
                'locked_until' => $lockedUntil->toDateTimeString(),
            ],
            actorUserId: $user->id,
            actorType: 'user',
        );

        Log::warning('Account locked due to failed attempts', [
            'user_id' => $user->id,
            'username' => $user->login_id,
            'attempts' => $attempts,
            'locked_until' => $lockedUntil,
            'ip' => $request->ip(),
        ]);
    }

    /**
     * Set progressive warning messages for failed attempts.
     */
    private function setProgressiveWarning(int $attempts, int $maxAttempts): void
    {
        $remaining = $maxAttempts - $attempts;
        
        $message = $remaining === 1
            ? 'Incorrect credentials. This is your last attempt before temporary lockout.'
            : sprintf('Incorrect credentials. You have %d attempts remaining before temporary lockout.', $remaining);
        
        session()->flash('warning', $message);
    }

    /**
     * Throw lockout exception with detailed message.
     */
    private function throwLockoutException(User $user): void
    {
        $lockedUntil = $user->locked_until;
        $minutesRemaining = max(1, $lockedUntil->diffInMinutes(now()));
        $unlockTime = $lockedUntil->format('g:i A');
        
        $message = sprintf(
            'Your account has been temporarily locked due to multiple failed login attempts. Please try again in %d %s (at %s).',
            $minutesRemaining,
            $minutesRemaining === 1 ? 'minute' : 'minutes',
            $unlockTime
        );
        
        // Store lockout info for display
        session()->flash('lockout_until', $lockedUntil->toDateTimeString());
        session()->flash('lockout_minutes', $minutesRemaining);
        
        throw ValidationException::withMessages([
            'login_id' => [$message],
        ]);
    }

    /**
     * Clear failed attempts from cache.
     */
    private function clearFailedAttempts(User $user, Request $request): void
    {
        Cache::forget($this->getFailedAttemptsKey($user, $request));
    }

    /**
     * Get cache key for failed attempts tracking.
     */
    private function getFailedAttemptsKey(User $user, Request $request): string
    {
        return 'login_attempts:' . $user->login_id . ':' . $request->ip();
    }

    private function trimAuditUsername(string $username): string
    {
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $username);

        return trim($sanitized ?? '');
    }

    private function canonicalUnknownLoginIdentifier(string $username): string
    {
        $normalized = Str::transliterate(Str::lower($username));

        if ($normalized === '') {
            return 'login_unknown';
        }

        return 'login_' . hash('sha256', $normalized);
    }

    /**
     * Regenerate session for security.
     */
    private function regenerateSession(Request $request): void
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->regenerate();
    }

    /**
     * Get redirect path after successful login.
     */
    public function getPostLoginRedirect(User $user): string
    {
        // Check for intended URL first
        if (session()->has('url.intended')) {
            return session()->pull('url.intended');
        }

        // Admin users go to admin dashboard
        if ($user->hasAnyPermission([
            'admin.system.view',
            'admin.users.view',
            'admin.jobs.view',
            'admin.applications.view',
            'admin.settings.view',
        ])) {
            return route('admin.dashboard');
        }

        // Company users go to jobs management
        if ($user->isCompany()) {
            return route('jobs.index');
        }

        // Individual users go to their applications
        if ($user->isIndividual()) {
            return route('my.applications.index');
        }

        // Default fallback
        return route('home');
    }
}
