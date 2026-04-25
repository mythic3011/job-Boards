<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\InstallCompletionCoordinator;
use App\Services\InstallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstallController extends Controller
{
    private const REQUEST_MAX_AGE_CHECKS = 300;

    private const REQUEST_MAX_AGE_COMPLETE = 1800;

    private const INSTALL_SESSION_KEY = 'IP_INSTALL_SESSION';

    private const INSTALL_SESSION_TIMEOUT = 3600;

    public function __construct(
        private readonly InstallService $installService,
        private readonly InstallCompletionCoordinator $installCompletionCoordinator,
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

        if (! $this->isInstallSessionValid($request)) {
            $this->installCompletionCoordinator->validateRequestAge($request, self::REQUEST_MAX_AGE_CHECKS);
        } else {
            $this->installCompletionCoordinator->validateRequestAge($request, self::REQUEST_MAX_AGE_CHECKS * 6);
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
     * Supports both JavaScript and Livewire installers.
     */
    public function complete(Request $request): JsonResponse
    {
        $this->normalizeCompletePayload($request);

        // Check if this is a Livewire request (no timestamp/session validation needed)
        $isLivewireRequest = ! $request->has('timestamp') || ! $request->has('session');

        $isActiveSession = false;
        if (! $isLivewireRequest) {
            // JavaScript installer - validate timestamp
            $this->validateCompleteRequest($request, requireReplayFields: true);

            $isActiveSession = $this->isInstallSessionValid($request);
        } else {
            // Livewire installer - same canonical validation rules, without replay fields
            $this->validateCompleteRequest($request, requireReplayFields: false);
        }

        $this->installCompletionCoordinator->enforceCompletionChecks(
            request: $request,
            checkReplayAndRateLimit: ! $isLivewireRequest,
            isActiveInstallSession: $isActiveSession,
            maxAge: self::REQUEST_MAX_AGE_COMPLETE
        );

        try {
            $this->logInstallAttempt($request);

            $installDemo = $request->boolean('install_demo_data') || $request->boolean('demo');

            $this->installService->completeInstallation([
                'admin_username' => $request->input('admin_username'),
                'admin_name' => $request->admin_name ?? $request->name,
                'admin_email' => $request->admin_email ?? $request->email,
                'admin_password' => $request->admin_password ?? $request->password,
                'two_factor_secret' => $request->input('two_factor_secret') ?? $request->input('twoFactorSecret'),
                'recovery_codes' => $request->input('recovery_codes') ?? $request->input('recoveryCodes') ?? [],
                'app_name' => $request->input('app_name'),
                'app_url' => $request->input('app_url'),
                'timezone' => $request->input('timezone'),
                'install_demo_data' => $installDemo,
            ]);

            if (! $isLivewireRequest) {
                $this->installCompletionCoordinator->clearRateLimit($request);
                $this->clearInstallSession($request);
            }

            return $this->secureJsonResponse([
                'success' => true,
                'message' => 'Installation completed successfully!',
                'redirect' => route('login'),
            ]);
        } catch (\Exception $e) {
            Log::error('Installation failed', [
                'error_class' => $e::class,
                'session' => $request->session()->getId(),
                'ip' => $request->ip(),
                'submitted_fields' => $this->safeInstallSubmittedFields($request),
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
    private function validateCompleteRequest(Request $request, bool $requireReplayFields): void
    {
        $request->validate($this->installCompletionCoordinator->completionValidationRules($requireReplayFields));
    }

    /**
     * Normalize install payload aliases into one canonical shape.
     */
    private function normalizeCompletePayload(Request $request): void
    {
        $adminPassword = $this->firstString($request, ['admin_password', 'password']);

        $request->merge([
            'admin_username' => $this->normalizeOptionalUsername($this->firstString($request, ['admin_username', 'username'])),
            'admin_name' => $this->firstString($request, ['admin_name', 'name']),
            'admin_email' => $this->firstString($request, ['admin_email', 'email']),
            'admin_password' => $adminPassword,
            'admin_password_confirmation' => $this->firstString(
                $request,
                ['admin_password_confirmation', 'password_confirmation']
            ) ?? $adminPassword,
            'two_factor_secret' => $this->firstString($request, ['two_factor_secret', 'twoFactorSecret']),
            'otp_code' => $this->firstString($request, ['otp_code']),
            'recovery_codes' => $this->firstArray($request, ['recovery_codes', 'recoveryCodes']),
            'install_demo_data' => $this->normalizeBoolean(
                $request->input('install_demo_data', $request->input('demo'))
            ),
            'demo' => $this->normalizeBoolean($request->input('demo')),
        ]);
    }

    /**
     * @param  list<string>  $keys
     */
    private function firstString(Request $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $request->input($key);
            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $keys
     */
    private function firstArray(Request $request, array $keys): array
    {
        foreach ($keys as $key) {
            $value = $request->input($key);
            if (is_array($value)) {
                return $value;
            }
        }

        return [];
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function normalizeOptionalUsername(?string $username): ?string
    {
        if ($username === null) {
            return null;
        }

        return Str::lower($username);
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
        $installDemo = $request->boolean('install_demo_data') || $request->boolean('demo');

        $this->auditLogger->logBusinessEvent(
            eventType: 'install.complete_attempt',
            request: $request,
            targetType: 'system',
            targetIdcode: null,
            meta: [
                'session' => $request->session()->getId(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'demo_data' => $installDemo,
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

        if (! $sessionData || ! is_array($sessionData)) {
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

    /**
     * Capture only non-sensitive field names for install failure diagnostics.
     *
     * @return list<string>
     */
    private function safeInstallSubmittedFields(Request $request): array
    {
        return array_values(array_keys($request->except([
            'admin_password',
            'admin_password_confirmation',
            'two_factor_secret',
            'recovery_codes',
            'otp_code',
            'session',
            'timestamp',
        ])));
    }
}
