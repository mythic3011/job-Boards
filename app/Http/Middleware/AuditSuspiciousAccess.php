<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditSuspiciousAccess
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('security_audit.enabled', true) || $this->shouldExclude($request)) {
            return $next($request);
        }

        $response = $next($request);

        $statusCode = $response->getStatusCode();
        $route = $request->route();
        $path = $request->path();
        $routeName = $route?->getName();
        $routeExists = $route !== null;
        $isGuest = !$request->user();

        $meta = [
            'route_name' => $routeName,
            'route_exists' => $routeExists,
            'is_guest' => $isGuest,
            'guard' => auth()->getDefaultDriver(),
        ];

        if ($this->isUnauthProtectedRouteAttempt($request, $statusCode)) {
            $this->auditLogger->logRequestEvent(
                eventType: 'security.unauth_access',
                request: $request,
                statusCode: $statusCode,
                targetType: 'route',
                targetIdcode: $path,
                meta: $meta + [
                    'reason' => 'Guest attempted to access protected route',
                    'route_middleware' => $this->routeMiddleware($request),
                ]
            );
        }

        if ($this->isProbeCandidate($request, $statusCode)) {
            $signals = $this->recordSignals($request, $statusCode);

            $this->auditLogger->logRequestEvent(
                eventType: 'security.route_probe',
                request: $request,
                statusCode: $statusCode,
                targetType: 'route',
                targetIdcode: $path,
                meta: $meta + $signals + [
                    'reason' => 'Suspicious route probing signal',
                    'risk_score' => $this->riskScore($request, $statusCode, $signals),
                    'high_risk_path' => $this->isHighRiskPath($path),
                ]
            );

            if ($this->isScanDetected($signals) && !$this->inDetectionCooldown($request)) {
                $this->auditLogger->logRequestEvent(
                    eventType: 'security.route_scan_detected',
                    request: $request,
                    statusCode: $statusCode,
                    targetType: 'ip',
                    targetIdcode: (string) ($request->ip() ?? 'unknown'),
                    meta: $meta + $signals + [
                        'reason' => 'Route scan threshold exceeded',
                        'risk_score' => $this->riskScore($request, $statusCode, $signals),
                    ]
                );

                $this->startDetectionCooldown($request);
            }
        }

        return $response;
    }

    private function shouldExclude(Request $request): bool
    {
        $excluded = config('security_audit.exclude_paths', []);

        foreach ($excluded as $pattern) {
            if (Str::is($pattern, $request->path())) {
                return true;
            }
        }

        return false;
    }

    private function isUnauthProtectedRouteAttempt(Request $request, int $statusCode): bool
    {
        if ($request->user()) {
            return false;
        }

        $statusMatch = in_array($statusCode, [401, 403, 404], true);
        if (!$statusMatch) {
            return false;
        }

        $middleware = $this->routeMiddleware($request);
        if (empty($middleware)) {
            return false;
        }

        foreach (config('security_audit.protected_route_middleware', ['auth', 'verified', 'admin.2fa', 'role:admin']) as $needle) {
            foreach ($middleware as $entry) {
                if (Str::contains($entry, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function routeMiddleware(Request $request): array
    {
        $route = $request->route();

        if (!$route || !method_exists($route, 'gatherMiddleware')) {
            return [];
        }

        return array_values(array_unique($route->gatherMiddleware()));
    }

    private function isProbeCandidate(Request $request, int $statusCode): bool
    {
        if (!in_array($statusCode, config('security_audit.probe_statuses', [403, 404, 405]), true)) {
            return false;
        }

        if ($this->isHighRiskPath($request->path())) {
            return true;
        }

        if (!$request->route()) {
            return true;
        }

        return !$request->user();
    }

    private function recordSignals(Request $request, int $statusCode): array
    {
        $windowMinutes = (int) config('security_audit.window_minutes', 2);
        $ttl = now()->addMinutes($windowMinutes);

        $signature = sha1(($request->ip() ?? 'unknown') . '|' . Str::substr((string) $request->userAgent(), 0, 80));
        $attemptsKey = "security_audit:attempts:{$signature}";
        $uniquePathsKey = "security_audit:unique_paths:{$signature}";
        $protectedHitsKey = "security_audit:protected_hits:{$signature}";

        Cache::add($attemptsKey, 0, $ttl);
        $attempts = (int) Cache::increment($attemptsKey);

        $pathKey = 'security_audit:path:' . $signature . ':' . md5((string) $request->path());
        if (!Cache::has($pathKey)) {
            Cache::put($pathKey, true, $ttl);
            Cache::add($uniquePathsKey, 0, $ttl);
            Cache::increment($uniquePathsKey);
        }

        if ($this->isUnauthProtectedRouteAttempt($request, $statusCode)) {
            Cache::add($protectedHitsKey, 0, $ttl);
            Cache::increment($protectedHitsKey);
        }

        return [
            'window_minutes' => $windowMinutes,
            'attempt_count' => $attempts,
            'unique_path_count' => (int) Cache::get($uniquePathsKey, 0),
            'unauth_protected_hits' => (int) Cache::get($protectedHitsKey, 0),
        ];
    }

    private function isScanDetected(array $signals): bool
    {
        return $signals['attempt_count'] >= (int) config('security_audit.thresholds.attempt_count', 20)
            || $signals['unique_path_count'] >= (int) config('security_audit.thresholds.unique_path_count', 12)
            || $signals['unauth_protected_hits'] >= (int) config('security_audit.thresholds.unauth_protected_hits', 6);
    }

    private function riskScore(Request $request, int $statusCode, array $signals): int
    {
        $score = 0;

        if (in_array($statusCode, [403, 404, 405], true)) {
            $score += 25;
        }

        if ($this->isHighRiskPath($request->path())) {
            $score += 35;
        }

        if (($signals['attempt_count'] ?? 0) >= (int) config('security_audit.thresholds.attempt_count', 20)) {
            $score += 20;
        }

        if (($signals['unique_path_count'] ?? 0) >= (int) config('security_audit.thresholds.unique_path_count', 12)) {
            $score += 15;
        }

        if (($signals['unauth_protected_hits'] ?? 0) >= (int) config('security_audit.thresholds.unauth_protected_hits', 6)) {
            $score += 20;
        }

        return min($score, 100);
    }

    private function isHighRiskPath(string $path): bool
    {
        $normalized = Str::lower(ltrim($path, '/'));

        foreach (config('security_audit.high_risk_path_patterns', []) as $pattern) {
            if (Str::is($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function inDetectionCooldown(Request $request): bool
    {
        return Cache::has($this->cooldownKey($request));
    }

    private function startDetectionCooldown(Request $request): void
    {
        $minutes = (int) config('security_audit.scan_detected_cooldown_minutes', 10);
        Cache::put($this->cooldownKey($request), true, now()->addMinutes($minutes));
    }

    private function cooldownKey(Request $request): string
    {
        $signature = sha1(($request->ip() ?? 'unknown') . '|' . Str::substr((string) $request->userAgent(), 0, 80));

        return "security_audit:scan_detected_cooldown:{$signature}";
    }
}
