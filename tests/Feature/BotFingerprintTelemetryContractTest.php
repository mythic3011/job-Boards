<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class BotFingerprintTelemetryContractTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        config([
            'cache.default' => 'array',
            'cache.stores.array' => [
                'driver' => 'array',
                'serialize' => false,
            ],
            'session.driver' => 'array',
        ]);
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('cache');
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('cache.store');
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('cache.psr6');
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('session');
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('session.store');
        \Illuminate\Support\Facades\Facade::clearResolvedInstance(RateLimiter::class);
        app()->forgetInstance('cache');
        app()->forgetInstance('cache.store');
        app()->forgetInstance('cache.psr6');
        app()->forgetInstance('session');
        app()->forgetInstance('session.store');
        app()->forgetInstance(RateLimiter::class);
        app('cache')->setDefaultDriver('array');
        app('session')->setDefaultDriver('array');
        $this->createSettingsTable();
        $this->createAuditLogsTable();

        Setting::setBool('maintenance_mode', false);
        Cache::forget('setting.maintenance_mode');
    }

    public function test_bot_fingerprint_route_explicitly_excludes_csrf_middleware_for_banned_page_beacons(): void
    {
        $route = app('router')->getRoutes()->getByName('bot.fp-log');

        $this->assertNotNull($route);
        $this->assertContains(\App\Http\Middleware\HoneypotProtection::class, $route->excludedMiddleware());
        $this->assertContains(VerifyCsrfToken::class, $route->excludedMiddleware());
        $this->assertContains(\App\Http\Middleware\CheckMaintenanceMode::class, $route->excludedMiddleware());
    }

    public function test_valid_banned_page_payload_is_normalized_before_it_is_audited(): void
    {
        $payload = [
            'fp' => str_repeat('a', 64),
            'headless' => false,
            'canvas_ok' => true,
            'webgl_vendor' => 'Intel Inc.',
            'ts' => now()->getTimestampMs(),
            'junk' => 'ignore-me',
        ];

        $response = $this->withBrowser()
            ->postJson('/api/bot/fp-log?probe=banned_page&signal=page_load', $payload);

        $response->assertNoContent();

        $log = AuditLog::query()->where('event_type', 'bot_fingerprint_probe')->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('security', $log->target_type);
        $this->assertSame('banned_page', $log->meta['probe']);
        $this->assertSame('page_load', $log->meta['signal']);
        $this->assertSame(hash('sha256', str_repeat('a', 64)), $log->meta['fp_sha256']);
        $this->assertTrue($log->meta['canvas_ok']);
        $this->assertFalse($log->meta['headless']);
        $this->assertSame('Intel Inc.', $log->meta['webgl_vendor']);
        $this->assertSame($payload['ts'], $log->meta['client_ts']);
        $this->assertIsInt($log->meta['server_ts']);
        $this->assertArrayNotHasKey('fp', $log->meta);
        $this->assertArrayNotHasKey('junk', $log->meta);
    }

    public function test_query_probe_contract_cannot_be_overridden_by_conflicting_body_fields(): void
    {
        $response = $this->withBrowser()
            ->postJson('/api/bot/fp-log?probe=banned_page&signal=page_load', [
                'probe' => 'banned_page',
                'signal' => 'mousemove',
                'fp' => str_repeat('c', 64),
                'ts' => now()->getTimestampMs(),
            ]);

        $response->assertNoContent();

        $log = AuditLog::query()->where('event_type', 'bot_fingerprint_probe')->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('banned_page', $log->meta['probe']);
        $this->assertSame('page_load', $log->meta['signal']);
    }

    public function test_banned_page_probe_route_remains_available_during_maintenance_mode(): void
    {
        Setting::setBool('maintenance_mode', true);
        Cache::forget('setting.maintenance_mode');

        $response = $this->withBrowser()
            ->postJson('/api/bot/fp-log?probe=banned_page&signal=page_load', [
                'fp' => str_repeat('d', 64),
                'ts' => now()->getTimestampMs(),
            ]);

        $response->assertNoContent();

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_malformed_probe_payload_is_rejected_without_creating_an_audit_log(): void
    {
        $response = $this->withBrowser()
            ->postJson('/api/bot/fp-log?probe=banned_page&signal=page_load', [
                'fp' => 'not-a-valid-fingerprint',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fp']);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_out_of_window_client_timestamp_is_rejected_without_creating_an_audit_log(): void
    {
        $response = $this->withBrowser()
            ->postJson('/api/bot/fp-log?probe=banned_page&signal=page_load', [
                'probe' => 'banned_page',
                'signal' => 'page_load',
                'fp' => str_repeat('b', 64),
                'ts' => now()->addMinutes(30)->getTimestampMs(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ts']);

        $this->assertSame(0, AuditLog::query()->count());
    }
}
