<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleSuspiciousUserAgent
{
    /**
     * Suspicious user agent patterns (defense-in-depth, not primary defense).
     */
    protected array $suspiciousPatterns = [
        'sqlmap', 'nikto', 'nmap', 'masscan', 'zap', 'burp', 'w3af',
        'acunetix', 'nessus', 'openvas', 'metasploit', 'havij', 'pangolin',
        'sqlsus', 'sqlninja', 'wpscan', 'joomscan', 'drupalscan', 'cmsmap',
        'whatweb', 'dirb', 'dirbuster', 'gobuster', 'wfuzz', 'ffuf',
        'dirsearch', 'feroxbuster', 'hydra', 'medusa', 'patator',
        'brutespray', 'ncrack', 'john', 'hashcat',
    ];

    /**
     * High-risk paths that should have stricter handling.
     */
    protected array $highRiskPaths = [
        '/admin',
        '/install',
        '/login',
        '/register',
        '/password',
    ];

    /**
     * Minimum user agent length to be considered valid.
     */
    private const MIN_USER_AGENT_LENGTH = 10;

    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * OWASP A09: Defense-in-depth approach - log, rate limit, challenge
     * instead of blocking (which can be bypassed by UA spoofing).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = $request->userAgent() ?? '';
        $isSuspicious = $this->isSuspicious($userAgent);
        $isHighRiskPath = $this->isHighRiskPath($request->path());

        if ($isSuspicious) {
            $this->handleSuspiciousUserAgent($request, $userAgent, $isHighRiskPath);

            // For high-risk paths, return 404 (hide existence) instead of 403
            if ($isHighRiskPath) {
                abort(404);
            }
        }

        // Block empty or very short user agents (likely automated)
        if (strlen($userAgent) < self::MIN_USER_AGENT_LENGTH) {
            $this->auditLogger->logRequestEvent(
                eventType: 'suspicious_user_agent',
                request: $request,
                statusCode: 403,
                meta: [
                    'user_agent' => $this->sanitizeUserAgent($userAgent),
                    'reason' => 'too_short',
                ]
            );

            abort(403, 'Access denied.');
        }

        return $next($request);
    }

    /**
     * Check if user agent matches suspicious patterns.
     */
    public function isSuspicious(string $userAgent): bool
    {
        $userAgentLower = strtolower($userAgent);

        foreach ($this->suspiciousPatterns as $pattern) {
            if (stripos($userAgentLower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the matched pattern for logging.
     */
    protected function getMatchedPattern(string $userAgent): ?string
    {
        $userAgentLower = strtolower($userAgent);

        foreach ($this->suspiciousPatterns as $pattern) {
            if (stripos($userAgentLower, $pattern) !== false) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * Check if path is high-risk.
     */
    protected function isHighRiskPath(string $path): bool
    {
        foreach ($this->highRiskPaths as $riskPath) {
            if (str_starts_with($path, $riskPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle suspicious user agent detection and logging.
     */
    private function handleSuspiciousUserAgent(Request $request, string $userAgent, bool $isHighRiskPath): void
    {
        // OWASP A09: Always log suspicious UA (for monitoring/detection)
        $this->auditLogger->logRequestEvent(
            eventType: 'suspicious_user_agent',
            request: $request,
            statusCode: 200, // Don't block yet, just log
            meta: [
                'user_agent' => $this->sanitizeUserAgent($userAgent),
                'matched_pattern' => $this->getMatchedPattern($userAgent),
                'is_high_risk_path' => $isHighRiskPath,
            ]
        );

        // For high-risk paths, log additional event
        if ($isHighRiskPath) {
            $this->auditLogger->logRequestEvent(
                eventType: 'suspicious_ua_high_risk_path',
                request: $request,
                statusCode: 404,
                meta: [
                    'user_agent' => $this->sanitizeUserAgent($userAgent),
                    'action' => 'returned_404',
                ]
            );
        }
    }

    /**
     * Sanitize user agent for logging (prevent log injection).
     */
    private function sanitizeUserAgent(string $userAgent): string
    {
        // Remove control characters and limit length
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $userAgent);
        return substr($sanitized, 0, 512);
    }
}
