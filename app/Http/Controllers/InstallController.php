<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\InstallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InstallController extends Controller
{
    private const REQUEST_MAX_AGE_CHECKS = 300;
    private const REQUEST_MAX_AGE_COMPLETE = 1800;
    private const REQUEST_TOLERANCE = 60;
    private const MAX_INSTALL_ATTEMPTS = 5;
    private const INSTALL_ATTEMPT_CACHE_TTL = 10;
    private const INSTALL_SESSION_KEY = 'IP_INSTALL_SESSION';
    private const INSTALL_SESSION_TIMEOUT = 3600;
    private const INSTALL_MAX_ATTEMPTS_DURING_SESSION = 20;

    public function __construct(
        private readonly InstallService $installService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Get installation status.
     */
    public function status(Request $request): JsonResponse
    {
        $status = $this->installService->isInstallationAllowed($request);

        return $this->secureJsonResponse([
            'allowed' => $status['allowed'],
            'issues' => $status['issues'],
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Display installation index page.
     */
    public function index(Request $request)
    {
        $this->setInstallSession($request);
        
        return view('install.index');
    }

    /**
     * Run system checks for installation.
     */
    public function checks(Request $request): JsonResponse
    {
        $this->validateChecksRequest($request);
        
        $this->setInstallSession($request);
        
        if (!$this->isInstallSessionValid($request)) {
            $this->validateRequestAge($request, self::REQUEST_MAX_AGE_CHECKS);
        } else {
            $this->validateRequestAge($request, self::REQUEST_MAX_AGE_CHECKS * 6);
        }

        try {
            $this->logCheckAttempt($request);

            $checks = $this->installService->runSystemChecks();

            return $this->secureJsonResponse([
                'checks' => $checks,
                'session' => $request->session()->getId(),
            ]);
        } catch (\Exception $e) {
            Log::error('Install checks failed', [
                'error' => $e->getMessage(),
                'session' => $request->session()->getId(),
                'ip' => $request->ip(),
            ]);

            return $this->secureJsonResponse([
                'checks' => [
                    'database' => false,
                    'storage' => false,
                    'cache' => false,
                    'error' => 'System check failed',
                ],
            ], 500);
        }
    }

    /**
     * Complete the installation process.
     */
    public function complete(Request $request): JsonResponse
    {
        $this->validateCompleteRequest($request);
        
        $isActiveSession = $this->isInstallSessionValid($request);
        
        if ($isActiveSession) {
            $requestAge = now()->timestamp - $request->timestamp;
            if ($requestAge > self::REQUEST_MAX_AGE_COMPLETE) {
                throw ValidationException::withMessages([
                    'timestamp' => 'Request expired',
                ]);
            }
        } else {
            $this->validateRequestAge($request, self::REQUEST_MAX_AGE_COMPLETE);
        }
        
        $this->checkSuspiciousActivity($request);
        
        if ($isActiveSession) {
            $this->checkRateLimitDuringInstall($request);
        } else {
            $this->checkRateLimit($request);
        }

        try {
            $this->logInstallAttempt($request);

            $this->installService->completeInstallation([
                'admin_name' => $request->admin_name,
                'admin_email' => $request->admin_email,
                'admin_password' => $request->admin_password,
                'two_factor_secret' => $request->input('two_factor_secret'),
                'install_demo_data' => $request->boolean('install_demo_data'),
            ]);

            $this->clearRateLimit($request);
            $this->clearInstallSession($request);

            return $this->secureJsonResponse([
                'success' => true,
                'message' => 'Installation completed successfully!',
                'redirect' => route('login'),
            ]);
        } catch (\Exception $e) {
            Log::error('Installation failed', [
                'error' => $e->getMessage(),
                'session' => $request->session()->getId(),
                'ip' => $request->ip(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->secureJsonResponse([
                'success' => false,
                'message' => 'Installation failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Validate checks request.
     */
    private function validateChecksRequest(Request $request): void
    {
        $request->validate([
            'timestamp' => 'required|integer',
            'session' => 'required|string|max:100',
        ]);
    }

    /**
     * Validate complete installation request.
     */
    private function validateCompleteRequest(Request $request): void
    {
        $request->validate([
            'admin_name' => 'required|string|max:255|regex:/^[a-zA-Z\s\-_\.]+$/',
            'admin_email' => 'required|email:rfc,dns|unique:users,email|max:255',
            'admin_password' => 'required|string|min:12|max:255|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
            'admin_password_confirmation' => 'required|string|same:admin_password',
            'two_factor_secret' => 'required|string|regex:/^[A-Z2-7]{16,}$/i',
            'install_demo_data' => 'boolean',
            'timestamp' => 'required|integer',
            'session' => 'required|string|max:100',
        ]);
    }

    /**
     * Validate request age to prevent replay attacks.
     */
    private function validateRequestAge(Request $request, int $maxAge): void
    {
        $requestAge = now()->timestamp - $request->timestamp;
        if ($requestAge > $maxAge || $requestAge < -self::REQUEST_TOLERANCE) {
            throw ValidationException::withMessages([
                'timestamp' => 'Request expired',
            ]);
        }
    }

    /**
     * Check for suspicious user agent patterns.
     */
    private function checkSuspiciousActivity(Request $request): void
    {
        if (app(\App\Http\Middleware\HandleSuspiciousUserAgent::class)->isSuspicious($request)) {
            Log::warning('Suspicious install attempt blocked', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'session' => $request->session()->getId(),
            ]);

            abort(403, 'Access denied');
        }
    }

    /**
     * Check rate limit for installation attempts.
     * Standard rate limiting for new sessions.
     */
    private function checkRateLimit(Request $request): void
    {
        $key = $this->getRateLimitKey($request);
        $attempts = Cache::get($key, 0);

        if ($attempts >= self::MAX_INSTALL_ATTEMPTS) {
            abort(429, 'Too many attempts. Try again later.');
        }

        Cache::put($key, $attempts + 1, now()->addMinutes(self::INSTALL_ATTEMPT_CACHE_TTL));
    }

    /**
     * Check rate limit during active installation session.
     * More lenient but still provides protection against abuse.
     */
    private function checkRateLimitDuringInstall(Request $request): void
    {
        $key = $this->getRateLimitKey($request) . '_install';
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

    /**
     * Clear rate limit cache.
     */
    private function clearRateLimit(Request $request): void
    {
        Cache::forget($this->getRateLimitKey($request));
    }

    /**
     * Get rate limit cache key.
     */
    private function getRateLimitKey(Request $request): string
    {
        return 'install_attempts_' . $request->ip();
    }

    /**
     * Log check attempt for audit purposes.
     */
    private function logCheckAttempt(Request $request): void
    {
        $this->auditLogger->logBusinessEvent(
            eventType: 'install.checks_attempt',
            request: $request,
            targetType: 'system',
            targetIdcode: null,
            meta: [
                'session' => $request->session()->getId(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );
    }

    /**
     * Log installation attempt for audit purposes.
     */
    private function logInstallAttempt(Request $request): void
    {
        $this->auditLogger->logBusinessEvent(
            eventType: 'install.complete_attempt',
            request: $request,
            targetType: 'system',
            targetIdcode: null,
            meta: [
                'session' => $request->session()->getId(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'demo_data' => $request->boolean('install_demo_data'),
            ]
        );
    }

    /**
     * Set installation session with IP binding and timestamp.
     */
    private function setInstallSession(Request $request): void
    {
        $request->session()->put(self::INSTALL_SESSION_KEY, [
            'ip' => $request->ip(),
            'started_at' => now()->timestamp,
        ]);
    }

    /**
     * Check if installation session is valid (exists, not expired, same IP).
     */
    private function isInstallSessionValid(Request $request): bool
    {
        $sessionData = $request->session()->get(self::INSTALL_SESSION_KEY);
        
        if (!$sessionData || !is_array($sessionData)) {
            return false;
        }

        // Check if session expired (1 hour timeout)
        $startedAt = $sessionData['started_at'] ?? 0;
        if (now()->timestamp - $startedAt > self::INSTALL_SESSION_TIMEOUT) {
            $this->clearInstallSession($request);
            return false;
        }

        // Verify IP matches (prevent session hijacking)
        if (($sessionData['ip'] ?? '') !== $request->ip()) {
            Log::warning('Install session IP mismatch detected', [
                'session_ip' => $sessionData['ip'] ?? 'unknown',
                'request_ip' => $request->ip(),
                'session_id' => $request->session()->getId(),
            ]);
            $this->clearInstallSession($request);
            return false;
        }

        return true;
    }

    /**
     * Clear installation session.
     */
    private function clearInstallSession(Request $request): void
    {
        $request->session()->forget(self::INSTALL_SESSION_KEY);
    }

    /**
     * Return JSON response with security headers.
     */
    private function secureJsonResponse(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'DENY')
            ->header('X-XSS-Protection', '1; mode=block');
    }
}
