<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Log a request event (for security/probe logging).
     */
    public function logRequestEvent(
        string $eventType,
        Request $request,
        int $statusCode,
        ?string $targetType = null,
        ?string $targetIdcode = null,
        array $meta = []
    ): void {
        $this->createAuditLog($eventType, $request, $statusCode, $targetType, $targetIdcode, $meta);
    }

    /**
     * Log a business audit event (for sensitive actions).
     */
    public function logBusinessEvent(
        string $eventType,
        Request $request,
        ?string $targetType = null,
        ?string $targetIdcode = null,
        array $meta = []
    ): void {
        // Business events are typically successful
        $this->createAuditLog($eventType, $request, 200, $targetType, $targetIdcode, $meta);
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
        array $meta
    ): void {
        $user = $request->user();

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'occurred_at' => now(),
            'request_id' => $request->attributes->get('request_id', (string) Str::uuid()),

            'actor_user_id' => $user?->id,
            'actor_type' => $user ? 'user' : 'guest',

            'event_type' => $eventType,

            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $statusCode,

            'ip' => $request->ip(),
            'user_agent' => $this->truncateUserAgent($request->userAgent()),

            'target_type' => $targetType,
            'target_idcode' => $targetIdcode,

            'meta' => $this->sanitize($meta),
        ]);
    }

    /**
     * Sanitize meta data to prevent log injection and remove sensitive fields.
     */
    private function sanitize(array $meta): array
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

        return $sanitized;
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
