<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class HostTlsShellContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_host_ufw_contract_is_parameterized_by_tls_mode_and_http_redirect_policy(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/host/02-host-ufw-base.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('TLS_MODE="${BT_HOST_TLS_MODE:-cloudflare-origin}"', $contents);
        $this->assertStringContainsString('ALLOW_HTTP_REDIRECT="${BT_ALLOW_HTTP_REDIRECT:-1}"', $contents);
        $this->assertStringContainsString('BT_HOST_TLS_MODE must be one of:', $contents);
        $this->assertStringContainsString('letsencrypt-http01', $contents);
        $this->assertStringContainsString('letsencrypt-dns01', $contents);
        $this->assertStringContainsString('allow 80/tcp comment ${BT_MANAGED_COMMENT}:http', $contents);
        $this->assertStringContainsString('allow 443/tcp comment ${BT_MANAGED_COMMENT}:https', $contents);
        $this->assertStringContainsString('if should_allow_http_port; then', $contents);
    }

    public function test_bootstrap_host_forwards_tls_firewall_policy_to_managed_ufw_step(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/bootstrap/bootstrap-host.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('BT_HOST_TLS_MODE="${BT_HOST_TLS_MODE:-cloudflare-origin}"', $contents);
        $this->assertStringContainsString('BT_ALLOW_HTTP_REDIRECT="${BT_ALLOW_HTTP_REDIRECT:-1}"', $contents);
        $this->assertStringContainsString('"${OPS_DIR}/host/02-host-ufw-base.sh" apply', $contents);
        $this->assertStringContainsString('BT_CERTBOT_DOMAIN="${BT_CERTBOT_DOMAIN:-}"', $contents);
        $this->assertStringContainsString('"${OPS_DIR}/host/05-host-certbot-renewal.sh" apply', $contents);
    }

    public function test_host_tls_runbook_covers_cloudflare_and_letsencrypt_renewal_boundaries(): void
    {
        $contents = file_get_contents($this->repoRoot.'/docs/runbooks/host-tls-modes.md');

        $this->assertIsString($contents);
        $this->assertStringContainsString('cloudflare-origin', $contents);
        $this->assertStringContainsString('letsencrypt-http01', $contents);
        $this->assertStringContainsString('letsencrypt-dns01', $contents);
        $this->assertStringContainsString('BT_ALLOW_HTTP_REDIRECT=0', $contents);
        $this->assertStringContainsString('Certbot', $contents);
        $this->assertStringContainsString('80/tcp', $contents);
        $this->assertStringContainsString('443/tcp', $contents);
    }

    public function test_host_certbot_contract_is_mode_aware_and_systemd_managed(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/host/05-host-certbot-renewal.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('TLS_MODE="${BT_HOST_TLS_MODE:-cloudflare-origin}"', $contents);
        $this->assertStringContainsString('BT_CERTBOT_DOMAIN', $contents);
        $this->assertStringContainsString('BT_CERTBOT_EMAIL', $contents);
        $this->assertStringContainsString('letsencrypt-http01|letsencrypt-dns01', $contents);
        $this->assertStringContainsString('certbot-renew@.service', $contents);
        $this->assertStringContainsString('certbot-renew@.timer', $contents);
        $this->assertStringContainsString('systemctl enable --now', $contents);
        $this->assertStringContainsString('cloudflare-origin', $contents);
        $this->assertStringContainsString('SKIPPED', $contents);
    }
}
