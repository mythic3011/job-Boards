<?php

namespace Tests\Feature\Internal;

use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class CanonicalAuditIngestionTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createAuditLogsTable();

        config([
            'canonical_audit_ingestion.max_request_bytes' => 8192,
            'canonical_audit_ingestion.max_clock_skew_seconds' => 300,
            'canonical_audit_ingestion.callers' => [
                'auth-service-key' => [
                    'secret' => 'ingestion-shared-secret',
                    'source' => 'auth-service',
                    'caller_identity' => 'auth-service',
                    'allowed_ips' => ['127.0.0.1'],
                ],
            ],
        ]);
    }

    public function test_admissible_signed_event_is_admitted_once(): void
    {
        $payload = [
            'event_type' => 'audit.auth.verify.denied',
            'request_id' => (string) Str::uuid(),
            'source' => 'auth-service',
            'outcome' => 'denied',
            'actor_type' => 'guest',
            'target_type' => 'monitoring_account',
            'target_identifier' => 'monitoring-admin',
            'occurred_at' => now()->subMinute()->toIso8601String(),
            'metadata' => [
                'reason' => 'invalid_credentials',
                'username' => 'monitoring-admin',
            ],
        ];

        $headers = $this->signatureHeaders($payload);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/internal/canonical-audit/events', $payload, $headers)
            ->assertStatus(202);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/internal/canonical-audit/events', $payload, $headers)
            ->assertStatus(202);

        $this->assertSame(1, AuditLog::query()->count());

        $log = AuditLog::query()->firstOrFail();

        $this->assertSame('auth-service', $log->source);
        $this->assertSame('denied', $log->outcome);
        $this->assertSame('audit.auth.verify.denied', $log->event_type);
        $this->assertSame('monitoring-admin', $log->target_idcode);
        $this->assertSame('/monitoring/auth/verify', $log->path);
        $this->assertSame('POST', $log->method);
        $this->assertSame(401, $log->status_code);
        $this->assertNotNull($log->admitted_at);
    }

    public function test_payload_with_unknown_top_level_field_is_rejected(): void
    {
        $payload = [
            'event_type' => 'audit.auth.verify.denied',
            'request_id' => (string) Str::uuid(),
            'source' => 'auth-service',
            'outcome' => 'denied',
            'actor_type' => 'guest',
            'target_type' => 'monitoring_account',
            'target_identifier' => 'monitoring-admin',
            'occurred_at' => now()->subMinute()->toIso8601String(),
            'metadata' => [
                'reason' => 'invalid_credentials',
            ],
            'debug_payload' => 'should-not-pass-through',
        ];

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/internal/canonical-audit/events', $payload, $this->signatureHeaders($payload))
            ->assertStatus(422);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_payload_with_unknown_metadata_key_is_rejected(): void
    {
        $payload = [
            'event_type' => 'audit.auth.verify.denied',
            'request_id' => (string) Str::uuid(),
            'source' => 'auth-service',
            'outcome' => 'denied',
            'actor_type' => 'guest',
            'target_type' => 'monitoring_account',
            'target_identifier' => 'monitoring-admin',
            'occurred_at' => now()->subMinute()->toIso8601String(),
            'metadata' => [
                'reason' => 'invalid_credentials',
                'raw_debug' => 'this-should-never-enter-canonical-db',
            ],
        ];

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/internal/canonical-audit/events', $payload, $this->signatureHeaders($payload))
            ->assertStatus(422);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_non_json_content_type_is_rejected(): void
    {
        $payload = [
            'event_type' => 'audit.auth.verify.denied',
            'request_id' => (string) Str::uuid(),
            'source' => 'auth-service',
            'outcome' => 'denied',
            'actor_type' => 'guest',
            'target_type' => 'monitoring_account',
            'target_identifier' => 'monitoring-admin',
            'occurred_at' => now()->subMinute()->toIso8601String(),
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/internal/canonical-audit/events',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '127.0.0.1',
                'CONTENT_TYPE' => 'text/plain',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CANONICAL_AUDIT_KEY_ID' => 'auth-service-key',
                'HTTP_X_CANONICAL_AUDIT_SIGNATURE' => hash_hmac('sha256', $body, 'ingestion-shared-secret'),
            ],
            $body,
        )->assertStatus(415);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_signed_ingestion_fails_closed_when_no_ip_allowlist_is_configured(): void
    {
        config([
            'canonical_audit_ingestion.callers' => [
                'auth-service-key' => [
                    'secret' => 'ingestion-shared-secret',
                    'source' => 'auth-service',
                    'caller_identity' => 'auth-service',
                    'allowed_ips' => [],
                    'allowed_cidrs' => [],
                ],
            ],
        ]);

        $payload = [
            'event_type' => 'audit.auth.verify.denied',
            'request_id' => (string) Str::uuid(),
            'source' => 'auth-service',
            'outcome' => 'denied',
            'actor_type' => 'guest',
            'target_type' => 'monitoring_account',
            'target_identifier' => 'monitoring-admin',
            'occurred_at' => now()->subMinute()->toIso8601String(),
        ];

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/internal/canonical-audit/events', $payload, $this->signatureHeaders($payload))
            ->assertStatus(403);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_payload_larger_than_limit_is_rejected(): void
    {
        config(['canonical_audit_ingestion.max_request_bytes' => 64]);

        $payload = [
            'event_type' => 'audit.auth.verify.denied',
            'request_id' => (string) Str::uuid(),
            'source' => 'auth-service',
            'outcome' => 'denied',
            'actor_type' => 'guest',
            'target_type' => 'monitoring_account',
            'target_identifier' => 'monitoring-admin',
            'occurred_at' => now()->subMinute()->toIso8601String(),
            'metadata' => [
                'reason' => str_repeat('x', 128),
            ],
        ];

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/internal/canonical-audit/events', $payload, $this->signatureHeaders($payload))
            ->assertStatus(413);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_payload_with_malformed_occurred_at_is_rejected(): void
    {
        $payload = [
            'event_type' => 'audit.auth.verify.denied',
            'request_id' => (string) Str::uuid(),
            'source' => 'auth-service',
            'outcome' => 'denied',
            'actor_type' => 'guest',
            'target_type' => 'monitoring_account',
            'target_identifier' => 'monitoring-admin',
            'occurred_at' => 'not-a-timestamp',
            'metadata' => [
                'reason' => 'invalid_credentials',
            ],
        ];

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/internal/canonical-audit/events', $payload, $this->signatureHeaders($payload))
            ->assertStatus(422);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_canonical_identity_is_backed_by_database_unique_constraint(): void
    {
        $requestId = (string) Str::uuid();

        AuditLog::query()->create([
            'id' => (string) Str::uuid(),
            'occurred_at' => now()->subMinute(),
            'admitted_at' => now(),
            'request_id' => $requestId,
            'source' => 'auth-service',
            'outcome' => 'denied',
            'actor_user_id' => null,
            'actor_type' => 'guest',
            'event_type' => 'audit.auth.verify.denied',
            'method' => 'POST',
            'path' => '/monitoring/auth/verify',
            'status_code' => 401,
            'ip' => null,
            'user_agent' => null,
            'target_type' => 'monitoring_account',
            'target_idcode' => 'monitoring-admin',
            'meta' => ['reason' => 'invalid_credentials'],
        ]);

        $this->expectException(QueryException::class);

        AuditLog::query()->create([
            'id' => (string) Str::uuid(),
            'occurred_at' => now(),
            'admitted_at' => now(),
            'request_id' => $requestId,
            'source' => 'auth-service',
            'outcome' => 'denied',
            'actor_user_id' => null,
            'actor_type' => 'guest',
            'event_type' => 'audit.auth.verify.denied',
            'method' => 'POST',
            'path' => '/monitoring/auth/verify',
            'status_code' => 401,
            'ip' => null,
            'user_agent' => null,
            'target_type' => 'monitoring_account',
            'target_idcode' => 'monitoring-admin',
            'meta' => ['reason' => 'invalid_credentials'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function signatureHeaders(array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return [
            'X-Canonical-Audit-Key-Id' => 'auth-service-key',
            'X-Canonical-Audit-Signature' => hash_hmac('sha256', $body, 'ingestion-shared-secret'),
        ];
    }
}
