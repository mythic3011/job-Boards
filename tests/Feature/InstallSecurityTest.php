<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\AntiBot\ChallengeVerifier;
use App\Services\AntiBot\ChallengeVerificationResult;
use App\Services\InstallService;
use Illuminate\Http\Request;
use Mockery;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class InstallSecurityTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        config([
            'app.install_guard_enabled' => true,
            'app.install_allowed_ips' => [],
            'app.install_token' => null,
            'anti_bot.enabled' => true,
            'anti_bot.surfaces.install.mode' => 'shadow',
        ]);

        $this->useInMemorySqlite();
        $this->createSettingsTable();
        $this->createAuditLogsTable();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_install_requires_explicit_bootstrap_guard_when_enabled(): void
    {
        $this->withBrowser()->get(route('install.index'))
            ->assertForbidden();
    }

    public function test_install_allows_valid_install_token_when_guard_enabled(): void
    {
        config(['app.install_token' => 'bootstrap-secret']);

        $this->withBrowser()->get('/install?token=bootstrap-secret')
            ->assertOk();
    }

    public function test_install_complete_requires_server_side_otp_code(): void
    {
        config(['app.install_token' => 'bootstrap-secret']);
        $this->createUsersTable();

        $installService = Mockery::mock(InstallService::class);
        $installService->shouldNotReceive('completeInstallation');
        $this->app->instance(InstallService::class, $installService);

        $this->withBrowser()->postJson('/install/complete?token=bootstrap-secret', [
            'admin_name' => 'Admin User',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'StrongPass123!',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['otp_code']);
    }

    public function test_install_enforcement_returns_challenge_required_when_step_up_is_triggered_without_token(): void
    {
        $this->enableInstallerEnforcement([
            'anti_bot.surfaces.install.thresholds.step_up' => 0,
        ]);

        $response = $this->installerRequest();

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Installer anti-bot challenge required.',
                'decision' => 'step_up_required',
                'deny_reason' => 'challenge_required',
                'challenge_required' => true,
            ]);
    }

    public function test_install_enforcement_returns_invalid_token_reason_when_verification_fails(): void
    {
        $this->enableInstallerEnforcement();
        $this->enableInstallerEnforcement([
            'anti_bot.surfaces.install.thresholds.step_up' => 0,
        ]);
        $this->app->instance(ChallengeVerifier::class, new class implements ChallengeVerifier
        {
            public function verify(Request $request, string $surface, ?string $token): ChallengeVerificationResult
            {
                return new ChallengeVerificationResult(
                    successful: false,
                    providerAvailable: true,
                    failureReason: 'invalid_token',
                );
            }
        });

        $response = $this->installerRequest([
            'HTTP_X_INSTALL_CHALLENGE_TOKEN' => 'bad-token',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Installer anti-bot challenge verification failed.',
                'decision' => 'challenge_failed',
                'deny_reason' => 'challenge_verification_failed',
                'challenge_required' => true,
            ]);
    }

    public function test_install_enforcement_fails_closed_when_provider_is_unavailable(): void
    {
        $this->enableInstallerEnforcement();
        $this->enableInstallerEnforcement([
            'anti_bot.surfaces.install.thresholds.step_up' => 0,
        ]);
        $this->app->instance(ChallengeVerifier::class, new class implements ChallengeVerifier
        {
            public function verify(Request $request, string $surface, ?string $token): ChallengeVerificationResult
            {
                return new ChallengeVerificationResult(
                    successful: false,
                    providerAvailable: false,
                    failureReason: 'provider_unavailable',
                );
            }
        });

        $response = $this->installerRequest([
            'HTTP_X_INSTALL_CHALLENGE_TOKEN' => 'some-token',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Installer anti-bot verification unavailable.',
                'decision' => 'degraded_fail_closed',
                'deny_reason' => 'provider_unavailable_strict_surface',
                'challenge_required' => true,
            ]);
    }

    public function test_install_enforcement_fails_closed_when_verifier_result_is_ambiguous(): void
    {
        $this->enableInstallerEnforcement();
        $this->enableInstallerEnforcement([
            'anti_bot.surfaces.install.thresholds.step_up' => 0,
        ]);
        $this->app->instance(ChallengeVerifier::class, new class implements ChallengeVerifier
        {
            public function verify(Request $request, string $surface, ?string $token): ChallengeVerificationResult
            {
                return new ChallengeVerificationResult(
                    successful: false,
                    providerAvailable: true,
                    failureReason: null,
                );
            }
        });

        $response = $this->installerRequest([
            'HTTP_X_INSTALL_CHALLENGE_TOKEN' => 'ambiguous-token',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Installer anti-bot verification could not be classified.',
                'decision' => 'degraded_fail_closed',
                'deny_reason' => 'policy_ambiguity',
                'challenge_required' => true,
            ]);
    }

    public function test_install_enforcement_allows_request_when_challenge_verification_succeeds(): void
    {
        $this->enableInstallerEnforcement();
        $this->enableInstallerEnforcement([
            'anti_bot.surfaces.install.thresholds.step_up' => 0,
        ]);
        $this->app->instance(ChallengeVerifier::class, new class implements ChallengeVerifier
        {
            public function verify(Request $request, string $surface, ?string $token): ChallengeVerificationResult
            {
                return new ChallengeVerificationResult(
                    successful: true,
                    providerAvailable: true,
                    failureReason: null,
                );
            }
        });

        $this->installerRequest([
            'HTTP_X_INSTALL_CHALLENGE_TOKEN' => 'good-token',
        ])->assertOk();
    }

    public function test_install_enforcement_records_deny_reason_in_audit_log(): void
    {
        $this->enableInstallerEnforcement([
            'anti_bot.surfaces.install.thresholds.step_up' => 0,
        ]);

        $this->installerRequest()->assertStatus(403);

        $log = AuditLog::query()->where('event_type', 'anti_bot.challenge_required')->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('anti_bot.challenge_required', $log->event_type);
        $this->assertSame('install', $log->meta['surface']);
        $this->assertSame('step_up_required', $log->meta['decision']);
        $this->assertSame('challenge_required', $log->meta['deny_reason']);
        $this->assertFalse($log->meta['shadow_mode']);
    }

    private function enableInstallerEnforcement(array $overrides = []): void
    {
        config(array_merge([
            'app.install_token' => 'bootstrap-secret',
            'anti_bot.enabled' => true,
            'anti_bot.surfaces.install.mode' => 'enforce',
            'anti_bot.surfaces.install.challenge_input_key' => 'X-Install-Challenge-Token',
            'anti_bot.surfaces.install.response.message' => 'Installer anti-bot challenge required.',
        ], $overrides));
    }

    private function installerRequest(array $server = [])
    {
        return $this->call('GET', '/install?token=bootstrap-secret', [], [], [], array_merge([
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; InstallSecurityTest/1.0)',
            'HTTP_ACCEPT' => 'application/json',
        ], $server));
    }
}
