<?php

namespace Tests\Feature;

use App\Services\InstallService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class TrustedProxyConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->rebootApplicationWithTrustedProxies(null, null);
    }

    public function test_default_configuration_does_not_honor_forwarded_headers(): void
    {
        $this->rebootApplicationWithTrustedProxies(null, null);

        $this->call('GET', '/_test/proxy-inspect', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'jobs.example.test',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->assertOk()
            ->assertJson([
                'ip' => '198.51.100.10',
                'secure' => false,
            ]);
    }

    protected function tearDown(): void
    {
        $this->setEnvValue('TRUSTED_PROXIES', null);
        $this->setEnvValue('TRUSTED_PROXY_HEADERS', null);

        parent::tearDown();
    }

    public function test_untrusted_remote_address_does_not_honor_forwarded_headers(): void
    {
        $this->rebootApplicationWithTrustedProxies('192.0.2.10', 'x_forwarded');

        $this->call('GET', '/_test/proxy-inspect', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'jobs.example.test',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->assertOk()
            ->assertJson([
                'ip' => '198.51.100.10',
                'secure' => false,
            ]);
    }

    public function test_trusted_remote_address_honors_forwarded_headers(): void
    {
        $this->rebootApplicationWithTrustedProxies('198.51.100.10', 'x_forwarded');

        $this->call('GET', '/_test/proxy-inspect', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'jobs.example.test',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->assertOk()
            ->assertJson([
                'ip' => '203.0.113.5',
                'secure' => true,
            ]);
    }

    public function test_install_https_gate_does_not_trust_untrusted_forwarded_proto(): void
    {
        $this->rebootApplicationWithTrustedProxies('192.0.2.10', 'x_forwarded');

        $this->call('GET', 'http://jobs.example.test/_test/install-allowed', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; TestBrowser/1.0)',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'jobs.example.test',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->assertOk()
            ->assertJsonPath('allowed', false)
            ->assertJsonPath('issues.0', 'HTTPS required for installation');
    }

    public function test_install_https_gate_accepts_https_from_trusted_proxy_chain(): void
    {
        $this->rebootApplicationWithTrustedProxies('198.51.100.10', 'x_forwarded');

        $this->call('GET', 'http://jobs.example.test/_test/install-allowed', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; TestBrowser/1.0)',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'jobs.example.test',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->assertOk()
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('issues', []);
    }

    private function rebootApplicationWithTrustedProxies(?string $trustedProxies, ?string $headers): void
    {
        $this->setEnvValue('TRUSTED_PROXIES', $trustedProxies);
        $this->setEnvValue('TRUSTED_PROXY_HEADERS', $headers);

        $this->refreshApplication();
        Route::get('/_test/proxy-inspect', function (Request $request) {
            return response()->json([
                'ip' => $request->ip(),
                'secure' => $request->secure(),
            ]);
        });

        Route::get('/_test/install-allowed', function (Request $request, InstallService $installService) {
            return response()->json($installService->isInstallationAllowed($request));
        });
    }

    private function setEnvValue(string $name, ?string $value): void
    {
        if ($value === null) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);

            return;
        }

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
