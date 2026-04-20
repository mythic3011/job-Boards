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

    public function test_internal_only_monitoring_policy_uses_custom_forbidden_renderer_contract(): void
    {
        $entrypoint = file_get_contents($this->repoRoot.'/docker/nginx/entrypoint.sh');
        $nginx = file_get_contents($this->repoRoot.'/docker/nginx/nginx.conf');
        $page = file_get_contents($this->repoRoot.'/docker/nginx/errors/403.html');

        $this->assertIsString($entrypoint);
        $this->assertIsString($nginx);
        $this->assertIsString($page);
        $this->assertStringContainsString("if (\$is_internal = 0) { rewrite ^ /_error/403 last; }", $entrypoint);
        $this->assertStringContainsString('location = /_error/403 {', $nginx);
        $this->assertStringContainsString("local response = ngx.location.capture('/403.html')", $nginx);
        $this->assertStringContainsString('body = string.gsub(', $nginx);
        $this->assertStringContainsString('<span class="reference-id" id="request-id"></span>', $nginx);
        $this->assertStringContainsString('<span class="reference-id" id="request-id">\' .. (ngx.var.blue_team_request_id_final or \'-\') .. \'</span>', $nginx);
        $this->assertStringContainsString('ngx.status = ngx.HTTP_FORBIDDEN', $nginx);
        $this->assertStringContainsString('<h1>Access restricted</h1>', $page);
        $this->assertStringContainsString('Request ID: <span class="reference-id" id="request-id"></span>', $page);
        $this->assertStringContainsString('Check your session, account, or network context and try again.', $page);
        $this->assertStringNotContainsString('nginx', $page);
        $this->assertStringNotContainsString('install, monitoring, and privileged routes', $page);
        $this->assertStringNotContainsString('policy', $page);
    }

    public function test_livewire_versioned_assets_bypass_the_generic_static_asset_404_rule(): void
    {
        $nginx = file_get_contents($this->repoRoot.'/docker/nginx/nginx.conf');

        $this->assertIsString($nginx);
        $this->assertStringContainsString('location ^~ /livewire {', $nginx);
        $this->assertStringContainsString('try_files $uri $uri/ /index.php?$query_string;', $nginx);
    }
}
