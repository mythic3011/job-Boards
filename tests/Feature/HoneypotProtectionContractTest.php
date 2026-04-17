<?php

namespace Tests\Feature;

use App\Http\Middleware\HoneypotProtection;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class HoneypotProtectionContractTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createAuditLogsTable();
    }

    public function test_missing_timing_token_is_treated_as_a_honeypot_hit_and_audited(): void
    {
        $response = $this->runMiddleware([
            'email' => 'user@example.test',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Request processed'], json_decode($response->getContent(), true));

        $log = AuditLog::query()->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('honeypot.triggered', $log->event_type);
        $this->assertSame('denied', $log->outcome);
        $this->assertSame('security', $log->target_type);
        $this->assertSame('honeypot', $log->target_idcode);
        $this->assertSame('missing_timing_token', $log->meta['reason']);
        $this->assertSame('forgot-password', $log->meta['surface']);
    }

    public function test_expired_timing_token_is_treated_as_a_honeypot_hit_and_audited(): void
    {
        config(['honeypot.max_time' => 30]);

        $response = $this->runMiddleware([
            'email' => 'user@example.test',
            '_timing' => encrypt(time() - 45),
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $log = AuditLog::query()->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('expired_timing_token', $log->meta['reason']);
        $this->assertSame(30, $log->meta['max_allowed']);
        $this->assertGreaterThan(30, $log->meta['elapsed']);
    }

    public function test_invalid_timing_token_is_recorded_as_a_structured_honeypot_event(): void
    {
        $response = $this->runMiddleware([
            'email' => 'user@example.test',
            '_timing' => 'not-a-valid-token',
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $log = AuditLog::query()->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('honeypot.triggered', $log->event_type);
        $this->assertSame('invalid_timing_token', $log->meta['reason']);
        $this->assertSame('forgot-password', $log->meta['surface']);
    }

    public function test_too_fast_submission_is_recorded_as_a_structured_honeypot_event(): void
    {
        config(['honeypot.min_time' => 3]);

        $response = $this->runMiddleware([
            'email' => 'user@example.test',
            '_timing' => encrypt(time()),
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $log = AuditLog::query()->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('submission_too_fast', $log->meta['reason']);
        $this->assertSame(3, $log->meta['min_required']);
        $this->assertLessThan(3, $log->meta['elapsed']);
    }

    public function test_filled_honeypot_field_is_recorded_without_logging_raw_field_content(): void
    {
        $honeypotFieldName = (string) config('honeypot.field_name', 'website');

        $response = $this->runMiddleware([
            'email' => 'user@example.test',
            $honeypotFieldName => 'https://spam.example',
            '_timing' => encrypt(time() - 10),
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $log = AuditLog::query()->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('filled_honeypot_field', $log->meta['reason']);
        $this->assertSame('denied', $log->outcome);
        $this->assertSame($honeypotFieldName, $log->meta['field_name']);
        $this->assertTrue($log->meta['field_filled']);
        $this->assertSame(strlen('https://spam.example'), $log->meta['field_length']);
        $this->assertArrayNotHasKey('honeypot_value', $log->meta);
    }

    protected function runMiddleware(array $payload): Response
    {
        $request = Request::create('/forgot-password', 'POST', $payload);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; HoneypotTest/1.0)');

        return app(HoneypotProtection::class)->handle(
            $request,
            static fn (): Response => response('', 204)
        );
    }
}
