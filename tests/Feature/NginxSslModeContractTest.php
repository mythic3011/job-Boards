<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class NginxSslModeContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_https_server_uses_generated_ssl_include_instead_of_hardcoded_cert_paths(): void
    {
        $contents = file_get_contents($this->repoRoot.'/docker/nginx/nginx.conf');

        $this->assertIsString($contents);
        $this->assertStringContainsString('include /etc/nginx/generated/ssl-mode.conf;', $contents);
        $this->assertStringNotContainsString('/etc/nginx/ssl/selfsigned.crt', $contents);
        $this->assertStringNotContainsString('/etc/nginx/ssl/selfsigned.key', $contents);
    }

    public function test_nginx_entrypoint_renders_three_ssl_modes_from_template(): void
    {
        $contents = file_get_contents($this->repoRoot.'/docker/nginx/entrypoint.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('SSL_MODE="${SSL_MODE:-self-signed}"', $contents);
        $this->assertStringContainsString('SSL_MODE_TEMPLATE="/etc/nginx/templates/ssl-mode.conf.tpl"', $contents);
        $this->assertStringContainsString('render_ssl_mode_conf()', $contents);
        $this->assertStringContainsString('case "${SSL_MODE}" in', $contents);
        $this->assertStringContainsString('self-signed)', $contents);
        $this->assertStringContainsString('cloudflare-origin)', $contents);
        $this->assertStringContainsString('letsencrypt)', $contents);
        $this->assertStringContainsString('build_self_signed_san_list()', $contents);
        $this->assertStringContainsString('self_signed_cert_needs_renewal()', $contents);
    }

    public function test_compose_contract_mounts_runtime_ssl_dir_and_forwards_ssl_mode_to_nginx(): void
    {
        foreach (['/compose.yaml', '/compose.app.yml'] as $path) {
            $contents = file_get_contents($this->repoRoot.$path);

            $this->assertIsString($contents);
            $this->assertStringContainsString('SSL_MODE: "${SSL_MODE:-self-signed}"', $contents, $path);
            $this->assertStringContainsString('SSL_CERT_DOMAIN: "${SSL_CERT_DOMAIN:-localhost}"', $contents, $path);
            $this->assertStringContainsString('./docker/nginx/templates:/etc/nginx/templates:ro', $contents, $path);
            $this->assertStringContainsString('${BT_STATE_DIR:-.blue-team-vm}/runtime/nginx-ssl:/etc/nginx/ssl', $contents, $path);
            $this->assertStringContainsString('${BT_STATE_DIR:-.blue-team-vm}/runtime/rendered/nginx.ssl-mode.conf:/etc/nginx/generated/ssl-mode.conf', $contents, $path);
        }
    }

    public function test_install_bootstrap_delegates_ssl_materialization_to_ops_script(): void
    {
        $install = file_get_contents($this->repoRoot.'/install.sh');
        $bootstrapScript = file_get_contents($this->repoRoot.'/ops/bootstrap/bootstrap-nginx-ssl.sh');

        $this->assertIsString($install);
        $this->assertIsString($bootstrapScript);
        $this->assertStringContainsString('prepare_nginx_ssl_runtime()', $install);
        $this->assertStringContainsString('"${ROOT_DIR}/ops/bootstrap/bootstrap-nginx-ssl.sh" prepare', $install);
        $this->assertStringContainsString('ssl_switch_action()', $install);
        $this->assertStringContainsString('"${ROOT_DIR}/ops/bootstrap/bootstrap-nginx-ssl.sh" switch', $install);
        $this->assertStringContainsString('configure_renew_cron()', $bootstrapScript);
        $this->assertStringContainsString('reload_nginx_if_running()', $bootstrapScript);
        $this->assertStringContainsString('CF_Token', $bootstrapScript);
        $this->assertStringContainsString('CF_Zone_ID', $bootstrapScript);
        $this->assertStringContainsString('SSL_SELF_SIGNED_ALT_NAMES', $bootstrapScript);
        $this->assertStringContainsString('SSL_CERT_ALT_NAMES', $bootstrapScript);
        $this->assertStringContainsString('normalize_self_signed_alt_name()', $bootstrapScript);
        $this->assertStringContainsString('acme.sh', $bootstrapScript);
        $this->assertStringContainsString('certbot', $bootstrapScript);
    }

    public function test_ssl_switch_only_persists_env_after_runtime_switch_succeeds(): void
    {
        $install = file_get_contents($this->repoRoot.'/install.sh');

        $this->assertIsString($install);

        $switchCallPos = strpos($install, '"${ROOT_DIR}/ops/bootstrap/bootstrap-nginx-ssl.sh" switch');
        $persistCallPos = strpos($install, 'persist_ssl_env_overrides', $switchCallPos === false ? 0 : $switchCallPos);

        $this->assertNotFalse($switchCallPos, 'install.sh should delegate runtime switching to bootstrap-nginx-ssl.sh.');
        $this->assertNotFalse($persistCallPos, 'install.sh should persist SSL env overrides after switching.');
        $this->assertGreaterThan($switchCallPos, $persistCallPos, 'ssl-switch should not rewrite .env before the runtime switch succeeds.');
    }

    public function test_advanced_env_template_and_setup_doc_describe_ssl_modes_and_prerequisites(): void
    {
        $envExample = file_get_contents($this->repoRoot.'/.env.advanced.example');
        $setup = file_get_contents($this->repoRoot.'/SETUP.md');

        $this->assertIsString($envExample);
        $this->assertIsString($setup);

        $this->assertStringContainsString('SSL_MODE=self-signed', $envExample);
        $this->assertStringContainsString('SSL_CERT_DOMAIN=localhost', $envExample);
        $this->assertStringContainsString('CF_Token=', $envExample);
        $this->assertStringContainsString('CF_Zone_ID=', $envExample);
        $this->assertStringContainsString('SSL_ACME_CLIENT=acme.sh', $envExample);

        $this->assertStringContainsString('self-signed', $setup);
        $this->assertStringContainsString('cloudflare-origin', $setup);
        $this->assertStringContainsString('letsencrypt', $setup);
        $this->assertStringContainsString('./setup.sh ssl-switch <mode>', $setup);
        $this->assertStringContainsString('DNS-01', $setup);
        $this->assertStringContainsString('CF_Token', $setup);
        $this->assertStringContainsString('auto-renew cron', $setup);
    }
}
