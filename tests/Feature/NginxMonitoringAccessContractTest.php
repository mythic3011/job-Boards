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
        $this->assertStringContainsString('MONITORING_GEO_CONF="${MONITORING_GENERATED_DIR}/monitoring-geo.conf"', $contents);
        $this->assertStringContainsString('MONITORING_ACCESS_CONF="${MONITORING_GENERATED_DIR}/monitoring-access.conf"', $contents);
        $this->assertStringContainsString('PRIVATE_NETWORK_ALLOW_CONF="/etc/nginx/includes/private-network-allow.conf"', $contents);
        $this->assertStringContainsString('Rendered ${PRIVATE_NETWORK_ALLOW_CONF} from MONITORING_ALLOWED_CIDRS fallback.', $contents);
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

    public function test_nginx_security_headers_and_cache_policy_contracts_for_zap_lane_two(): void
    {
        $nginx = file_get_contents($this->repoRoot.'/docker/nginx/nginx.conf');

        $this->assertIsString($nginx);
        $this->assertStringContainsString('add_header X-Content-Type-Options "nosniff" always;', $nginx);
        $this->assertStringContainsString('add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;', $nginx);
        $this->assertStringContainsString('add_header Referrer-Policy "strict-origin-when-cross-origin" always;', $nginx);
        $this->assertStringContainsString('add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;', $nginx);
        $this->assertStringContainsString('add_header Cross-Origin-Opener-Policy "same-origin" always;', $nginx);
        $this->assertStringContainsString('add_header Cross-Origin-Resource-Policy "same-origin" always;', $nginx);
        $this->assertStringContainsString('add_header Cache-Control "public, max-age=604800, immutable" always;', $nginx);
        $this->assertStringContainsString('add_header Cache-Control "no-store, max-age=0" always;', $nginx);
        $this->assertStringContainsString('add_header Pragma "no-cache" always;', $nginx);
        $this->assertStringContainsString('location = /robots.txt {', $nginx);
        $this->assertStringContainsString('location = /sitemap.xml {', $nginx);
        $this->assertStringContainsString('add_header Cache-Control "public, max-age=3600" always;', $nginx);

        foreach (['location ~* \.(css|js|ico|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot|map)$ {', 'location = /robots.txt {', 'location = /sitemap.xml {', 'location ^~ /livewire {'] as $location) {
            $offset = strpos($nginx, $location);

            $this->assertNotFalse($offset, "Expected nginx location block [{$location}] to exist.");

            $block = substr($nginx, $offset, strpos($nginx, "\n        }\n", $offset) - $offset);

            $this->assertStringContainsString('add_header X-Content-Type-Options "nosniff" always;', $block);
            $this->assertStringContainsString('add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;', $block);
            $this->assertStringContainsString('add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;', $block);
            $this->assertStringContainsString('add_header Cross-Origin-Embedder-Policy "require-corp" always;', $block);
            $this->assertStringContainsString('add_header Cross-Origin-Opener-Policy "same-origin" always;', $block);
            $this->assertStringContainsString('add_header Cross-Origin-Resource-Policy "same-origin" always;', $block);
        }

        $sitemapOffset = strpos($nginx, 'location = /sitemap.xml {');
        $this->assertNotFalse($sitemapOffset, 'Expected sitemap location block to exist.');
        $sitemapBlock = substr($nginx, $sitemapOffset, strpos($nginx, "\n        }\n", $sitemapOffset) - $sitemapOffset);
        $this->assertStringContainsString('add_header Content-Security-Policy "default-src \'none\'; frame-ancestors \'none\'; base-uri \'none\'; form-action \'none\'" always;', $sitemapBlock);
    }

    public function test_install_sensitive_query_sanitization_contract_is_declared_in_nginx(): void
    {
        $nginx = file_get_contents($this->repoRoot.'/docker/nginx/nginx.conf');

        $this->assertIsString($nginx);
        $this->assertStringContainsString('map $request_method:$query_string $install_sensitive_query {', $nginx);
        $this->assertStringContainsString('~*^GET:.*(password|password_confirmation|email|username|name)= 1;', $nginx);
        $this->assertStringContainsString('location = /install {', $nginx);
        $this->assertStringContainsString('if ($install_sensitive_query) {', $nginx);
        $this->assertStringContainsString('return 418;', $nginx);
        $this->assertStringContainsString('error_page 418 = @install_sensitive_query;', $nginx);
        $this->assertStringContainsString('location @install_sensitive_query {', $nginx);
        $this->assertStringContainsString('add_header Content-Security-Policy "default-src \'none\'; frame-ancestors \'none\'; base-uri \'none\'; form-action \'none\'" always;', $nginx);
        $this->assertStringContainsString('return 404 "";', $nginx);
    }
}
