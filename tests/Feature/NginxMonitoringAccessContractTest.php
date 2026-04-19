<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class NginxMonitoringAccessContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_nginx_monitoring_access_policy_is_delegated_to_generated_runtime_includes(): void
    {
        $contents = file_get_contents($this->repoRoot.'/docker/nginx/nginx.conf');

        $this->assertIsString($contents);
        $this->assertStringContainsString('include /etc/nginx/generated/monitoring-geo.conf;', $contents);
        $this->assertStringContainsString('include /etc/nginx/generated/monitoring-access.conf;', $contents);
        $this->assertStringNotContainsString('100.122.13.62', $contents);
        $this->assertStringNotContainsString('100.71.55.114', $contents);
        $this->assertStringNotContainsString('100.114.61.64', $contents);
        $this->assertStringNotContainsString('if ($is_internal = 0) { return 403; }', $contents);
    }

    public function test_nginx_entrypoint_renders_monitoring_policy_from_runtime_env_contract(): void
    {
        $contents = file_get_contents($this->repoRoot.'/docker/nginx/entrypoint.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('MONITORING_ACCESS_MODE', $contents);
        $this->assertStringContainsString('MONITORING_ALLOWED_CIDRS', $contents);
        $this->assertStringContainsString('/etc/nginx/generated/monitoring-geo.conf', $contents);
        $this->assertStringContainsString('/etc/nginx/generated/monitoring-access.conf', $contents);
        $this->assertStringContainsString('internal-only', $contents);
        $this->assertStringContainsString('auth-only', $contents);
        $this->assertStringContainsString('disabled', $contents);
    }
}
