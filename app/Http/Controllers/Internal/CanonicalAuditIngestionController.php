<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\CanonicalAuditContract;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

class CanonicalAuditIngestionController extends Controller
{
    public function __invoke(Request $request, CanonicalAuditContract $contract): JsonResponse
    {
        if ($this->normalizedContentType($request) !== 'application/json') {
            return response()->json(['message' => 'Unsupported media type'], 415);
        }

        if (strlen($request->getContent()) > (int) config('canonical_audit_ingestion.max_request_bytes', 8192)) {
            return response()->json(['message' => 'Payload too large'], 413);
        }

        $caller = $this->resolveCaller($request);
        if ($caller === null || ! $this->hasValidSignature($request, $caller)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = $request->json()->all();
        $occurredAt = $this->parseOccurredAt($payload['occurred_at'] ?? null);

        if (! $this->isValidPayload($payload, $contract, $caller['source'], $occurredAt)) {
            return response()->json(['message' => 'Invalid canonical audit payload'], 422);
        }

        $log = AuditLog::query()->firstOrCreate(
            [
                'source' => (string) $payload['source'],
                'request_id' => (string) $payload['request_id'],
                'event_type' => (string) $payload['event_type'],
                'outcome' => (string) $payload['outcome'],
                'target_idcode' => (string) $payload['target_identifier'],
            ],
            [
                'id' => (string) Str::uuid(),
                'occurred_at' => $occurredAt,
                'admitted_at' => now(),
                'actor_user_id' => null,
                'actor_type' => (string) $payload['actor_type'],
                'method' => $this->methodForEvent((string) $payload['event_type']),
                'path' => $this->pathForEvent((string) $payload['event_type']),
                'status_code' => $this->statusCodeForOutcome((string) $payload['outcome']),
                'ip' => null,
                'user_agent' => null,
                'target_type' => (string) $payload['target_type'],
                'meta' => Arr::get($payload, 'metadata'),
            ],
        );

        return response()->json([
            'status' => $log->wasRecentlyCreated ? 'admitted' : 'duplicate',
        ], 202);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCaller(Request $request): ?array
    {
        $keyId = trim((string) $request->header('X-Canonical-Audit-Key-Id', ''));
        if ($keyId === '') {
            return null;
        }

        /** @var array<string, array<string, mixed>> $callers */
        $callers = config('canonical_audit_ingestion.callers', []);
        $caller = $callers[$keyId] ?? null;
        if (! is_array($caller)) {
            return null;
        }

        /** @var array<int, string> $allowedIps */
        $allowedIps = $caller['allowed_ips'] ?? [];
        /** @var array<int, string> $allowedCidrs */
        $allowedCidrs = $caller['allowed_cidrs'] ?? [];
        $remoteIp = (string) $request->ip();

        $matchesExactIp = $allowedIps !== [] && in_array($remoteIp, $allowedIps, true);
        $matchesAllowedCidr = $allowedCidrs !== [] && IpUtils::checkIp($remoteIp, $allowedCidrs);

        if (($allowedIps !== [] || $allowedCidrs !== []) && ! $matchesExactIp && ! $matchesAllowedCidr) {
            return null;
        }

        return $caller;
    }

    /**
     * @param  array<string, mixed>  $caller
     */
    private function hasValidSignature(Request $request, array $caller): bool
    {
        $secret = trim((string) ($caller['secret'] ?? ''));
        $signature = trim((string) $request->header('X-Canonical-Audit-Signature', ''));

        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isValidPayload(
        array $payload,
        CanonicalAuditContract $contract,
        string $expectedSource,
        ?CarbonImmutable $occurredAt,
    ): bool
    {
        $allowedFields = $contract->allowedPayloadFields();
        $requiredFields = $contract->requiredPayloadFields();

        foreach (array_keys($payload) as $field) {
            if (! in_array($field, $allowedFields, true)) {
                return false;
            }
        }

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                return false;
            }

            if (is_array($payload[$field]) || is_object($payload[$field])) {
                return false;
            }
        }

        if (! $contract->isAdmissibleEvent((string) $payload['event_type'])) {
            return false;
        }

        if ((string) $payload['source'] !== $expectedSource) {
            return false;
        }

        if ((string) $payload['outcome'] !== $contract->eventOutcome((string) $payload['event_type'])) {
            return false;
        }

        if (! in_array((string) $payload['source'], $contract->normalizedEnum('source'), true)) {
            return false;
        }

        if (! in_array((string) $payload['outcome'], $contract->normalizedEnum('outcome'), true)) {
            return false;
        }

        if ($occurredAt === null) {
            return false;
        }

        $maxClockSkewSeconds = (int) config('canonical_audit_ingestion.max_clock_skew_seconds', 300);
        if ($occurredAt->diffInSeconds(now(), absolute: true) > $maxClockSkewSeconds) {
            return false;
        }

        $metadata = Arr::get($payload, 'metadata');
        if ($metadata !== null) {
            if (! is_array($metadata)) {
                return false;
            }

            /** @var array<int, string> $allowedMetadataKeys */
            $allowedMetadataKeys = Arr::get($contract->definition(), 'metadata.allowed_keys', []);
            if (count($metadata) > $contract->metadataKeyLimit()) {
                return false;
            }

            foreach ($metadata as $key => $value) {
                if (! is_string($key) || ! in_array($key, $allowedMetadataKeys, true)) {
                    return false;
                }

                if (is_array($value) || is_object($value)) {
                    return false;
                }

                if (Str::length((string) $value) > $contract->metadataValueLengthLimit()) {
                    return false;
                }
            }
        }

        return true;
    }

    private function parseOccurredAt(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function methodForEvent(string $eventType): string
    {
        return match (true) {
            str_starts_with($eventType, 'audit.auth.check.') => 'GET',
            default => 'POST',
        };
    }

    private function pathForEvent(string $eventType): string
    {
        return match (true) {
            str_starts_with($eventType, 'audit.auth.verify.') => '/monitoring/auth/verify',
            str_starts_with($eventType, 'audit.auth.check.') => '/monitoring/auth/check',
            $eventType === 'audit.auth.locked' => '/monitoring/auth/verify',
            $eventType === 'audit.auth.logout' => '/monitoring/auth/logout',
            default => '/monitoring/auth',
        };
    }

    private function statusCodeForOutcome(string $outcome): int
    {
        return match ($outcome) {
            'denied' => 401,
            'rate_limited' => 429,
            default => 200,
        };
    }

    private function normalizedContentType(Request $request): string
    {
        $contentType = trim((string) $request->header('Content-Type', ''));

        return strtolower(trim(strtok($contentType, ';') ?: ''));
    }
}
