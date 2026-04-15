<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public function __construct(
        private readonly CanonicalAuditContract $canonicalAuditContract,
    ) {
    }

    /**
     * Log a security-related event.
     */
    public function logSecurityEvent(
        string $eventType,
        Request $request,
        ?string $userId = null,
        array $meta = [],
        int $statusCode = 200,
        ?string $actorUserId = null,
        ?string $actorType = null,
    ): void {
        $this->createAuditLog(
            eventType: $eventType,
            request: $request,
            statusCode: $statusCode,
            targetType: $userId ? 'user' : null,
            targetIdcode: $userId,
            meta: $meta,
            actorUserId: $actorUserId,
            actorType: $actorType,
        );
    }

    /**
     * Log a request event (for security/probe logging).
     */
    public function logRequestEvent(
        string $eventType,
        Request $request,
        int $statusCode,
        ?string $targetType = null,
        ?string $targetIdcode = null,
        array $meta = [],
        ?string $actorUserId = null,
        ?string $actorType = null,
    ): void {
        $this->createAuditLog($eventType, $request, $statusCode, $targetType, $targetIdcode, $meta, $actorUserId, $actorType);
    }

    /**
     * Log a business audit event (for sensitive actions).
     */
    public function logBusinessEvent(
        string $eventType,
        Request $request,
        ?string $targetType = null,
        ?string $targetIdcode = null,
        array $meta = [],
        ?string $actorUserId = null,
        ?string $actorType = null,
    ): void {
        // Business events are typically successful
        $this->createAuditLog($eventType, $request, 200, $targetType, $targetIdcode, $meta, $actorUserId, $actorType);
    }

    /**
     * Create an audit log entry.
     */
    private function createAuditLog(
        string $eventType,
        Request $request,
        int $statusCode,
        ?string $targetType,
        ?string $targetIdcode,
        array $meta,
        ?string $actorUserId = null,
        ?string $actorType = null,
    ): void {
        $user = $request->user();
        $resolvedActorUserId = $actorUserId ?? $user?->id;
        $resolvedActorType = $actorType ?? ($user ? 'user' : 'guest');
        $requestId = (string) $request->attributes->get('request_id', (string) Str::uuid());
        $attributes = [
            'source' => 'laravel',
            'request_id' => $requestId,
            'event_type' => $eventType,
            'outcome' => $this->canonicalOutcome($eventType, $statusCode),
            'target_idcode' => $targetIdcode,
        ];
        $values = [
            'id' => (string) Str::uuid(),
            'occurred_at' => now(),
            'admitted_at' => now(),
            'actor_user_id' => $resolvedActorUserId,
            'actor_type' => $resolvedActorType,
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $statusCode,
            'ip' => $request->ip(),
            'user_agent' => $this->truncateUserAgent($request->userAgent()),
            'target_type' => $targetType,
            'meta' => $this->sanitize($meta, $eventType),
        ];

        if ($this->canonicalAuditContract->isAdmissibleEvent($eventType)) {
            AuditLog::query()->createOrFirst($attributes, $values);

            return;
        }

        AuditLog::query()->create($attributes + $values);
    }

    /**
     * Sanitize meta data to prevent log injection and remove sensitive fields.
     */
    private function sanitize(array $meta, string $eventType): array
    {
        // Remove sensitive fields (OWASP A09 recommendation)
        $blocked = [
            'password',
            'password_confirmation',
            'token',
            'csrf_token',
            '_token',
            'code',
            'two_factor_code',
            'secret',
            'api_key',
            'api_secret',
        ];

        foreach ($blocked as $key) {
            unset($meta[$key]);
        }

        // Sanitize string values to prevent log injection
        $sanitized = [];
        foreach ($meta as $key => $value) {
            if (is_string($value)) {
                // Remove newlines and control characters
                $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
                // Truncate very long strings
                if (strlen($value) > 1000) {
                    $value = substr($value, 0, 1000) . '... [truncated]';
                }
            }
            $sanitized[$key] = $value;
        }

        if ($this->canonicalAuditContract->isAdmissibleEvent($eventType)) {
            return $this->sanitizeCanonicalMetadata($sanitized);
        }

        return $sanitized;
    }

    /**
     * Canonical events must stay within the shared contract metadata boundary.
     */
    private function sanitizeCanonicalMetadata(array $meta): array
    {
        $allowedKeys = array_flip($this->canonicalAuditContract->allowedMetadataKeys());
        $maxKeys = $this->canonicalAuditContract->metadataKeyLimit();
        $maxValueLength = $this->canonicalAuditContract->metadataValueLengthLimit();
        $sanitized = [];

        foreach ($meta as $key => $value) {
            if (count($sanitized) >= $maxKeys) {
                break;
            }

            if (! is_string($key) || ! array_key_exists($key, $allowedKeys)) {
                continue;
            }

            if (is_array($value) || is_object($value)) {
                continue;
            }

            $normalized = preg_replace('/[\x00-\x1F\x7F]/', '', (string) $value);
            $normalized = trim($normalized ?? '');

            if ($normalized === '') {
                continue;
            }

            $sanitized[$key] = substr($normalized, 0, $maxValueLength);
        }

        return $sanitized;
    }

    private function canonicalOutcome(string $eventType, int $statusCode): string
    {
        $contractOutcome = $this->canonicalAuditContract->eventOutcome($eventType);
        if ($contractOutcome !== null) {
            return $contractOutcome;
        }

        if ($eventType === 'audit.auth.logout' || str_contains($eventType, 'logout')) {
            return 'logout';
        }

        if ($statusCode === 429) {
            return 'rate_limited';
        }

        if ($statusCode >= 400) {
            return 'denied';
        }

        return 'success';
    }

    /**
     * Truncate user agent to reasonable length.
     */
    private function truncateUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        return substr($userAgent, 0, 1024);
    }
}
