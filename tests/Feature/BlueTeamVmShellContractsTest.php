<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Verification path: sqlite-safe.
 */
class BlueTeamVmShellContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_obs_apply_fails_before_compose_when_monitoring_hash_is_not_runtime_usable(): void
    {
        $tempDir = $this->makeTempDir();
        $dockerLog = $tempDir.'/docker.log';
        $fakeBin = $this->makeFakeDockerBin($tempDir, $dockerLog);
        $grafanaPasswordFile = $tempDir.'/grafana-admin-secret';
        file_put_contents($grafanaPasswordFile, $this->fixtureGrafanaAdminSecretContents());
        file_put_contents($tempDir.'/compose.obs.yml', "services: {}\n");

        $process = $this->runScript(
            [$this->repoRoot.'/ops/bootstrap/bootstrap-obs.sh', 'apply'],
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_COMPOSE_OBS_FILE' => $tempDir.'/compose.obs.yml',
                'BT_STATE_DIR' => $tempDir.'/state',
                'BT_RUNTIME_DIR' => $tempDir.'/state/runtime',
                'BT_OBS_GENERATED_ENV_FILE' => $tempDir.'/state/runtime/obs.generated.env',
                'BT_OBS_GENERATED_AUDIT_FILE' => $tempDir.'/state/runtime/obs.generated-secrets.jsonl',
                'BT_OBS_RENDERED_DIR' => $tempDir.'/state/rendered',
                'BT_GRAFANA_ADMIN_SECRET_FILE' => $grafanaPasswordFile,
                'MONITORING_ADMIN_USERNAME' => 'admin',
                'MONITORING_PASSWORD_HASH' => 'not-a-valid-bcrypt',
                'SESSION_SECRET' => str_repeat('a', 64),
                'GRAFANA_ADMIN_SECRET_FILE' => $grafanaPasswordFile,
                'PROMETHEUS_PASSWORD_HASH' => password_hash($this->fixturePlainCredential('prometheus'), PASSWORD_BCRYPT),
            ],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('"check_id":"obs.bootstrap.required_env"', $combinedOutput);
        $this->assertStringContainsString('"status":"FAIL"', $combinedOutput);
        $this->assertStringNotContainsString(' up -d', @file_get_contents($dockerLog) ?: '');
    }

    public function test_obs_prepare_materializes_runtime_artifacts_without_running_compose(): void
    {
        $tempDir = $this->makeTempDir();
        $dockerLog = $tempDir.'/docker.log';
        $fakeBin = $this->makeFakeDockerBin($tempDir, $dockerLog);
        file_put_contents($tempDir.'/compose.obs.yml', "services: {}\n");

        $process = $this->runScript(
            [$this->repoRoot.'/ops/bootstrap/bootstrap-obs.sh', 'prepare'],
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_COMPOSE_OBS_FILE' => $tempDir.'/compose.obs.yml',
                'BT_STATE_DIR' => $tempDir.'/state',
                'BT_RUNTIME_DIR' => $tempDir.'/state/runtime',
                'BT_OBS_GENERATED_ENV_FILE' => $tempDir.'/state/runtime/obs.generated.env',
                'BT_OBS_GENERATED_AUDIT_FILE' => $tempDir.'/state/runtime/obs.generated-secrets.jsonl',
                'BT_OBS_RENDERED_DIR' => $tempDir.'/state/rendered',
                'MONITORING_ADMIN_USERNAME' => 'admin',
                'DB_DATABASE' => 'jobs_boards',
                'DB_USERNAME' => 'jobs_boards',
                'DB_PASSWORD' => 'postgres-secret',
                'CANONICAL_AUDIT_AUTH_SERVICE_SECRET' => str_repeat('c', 64),
                'MONITORING_PASSWORD' => $this->fixturePlainCredential('monitoring'),
                'GRAFANA_PASSWORD' => $this->fixturePlainCredential('grafana'),
                'PROMETHEUS_PASSWORD' => $this->fixturePlainCredential('prometheus'),
            ],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $generatedEnv = @file_get_contents($tempDir.'/state/runtime/obs.generated.env') ?: '';
        $renderedConfig = $tempDir.'/state/rendered/prometheus.web-config.yml';
        $renderedGrafanaDatasources = $tempDir.'/state/rendered/grafana.datasources.yml';
        $grafanaPasswordFile = $tempDir.'/state/runtime/grafana-admin-secret';

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('"check_id":"obs.bootstrap.required_env"', $combinedOutput);
        $this->assertStringContainsString('"status":"PASS"', $combinedOutput);
        $this->assertStringContainsString('PROMETHEUS_WEB_CONFIG_FILE='.$renderedConfig, $generatedEnv);
        $this->assertStringContainsString('GRAFANA_DATASOURCES_FILE='.$renderedGrafanaDatasources, $generatedEnv);
        $this->assertStringContainsString('GRAFANA_ADMIN_SECRET_FILE='.$grafanaPasswordFile, $generatedEnv);
        $this->assertStringContainsString("MONITORING_ADMIN_USERNAME=admin\n", $generatedEnv);
        $this->assertStringContainsString("DB_DATABASE=jobs_boards\n", $generatedEnv);
        $this->assertStringContainsString("DB_USERNAME=jobs_boards\n", $generatedEnv);
        $this->assertStringContainsString("GRAFANA_POSTGRES_SECRET=postgres-secret\n", $generatedEnv);
        $this->assertStringContainsString('CANONICAL_AUDIT_AUTH_SERVICE_SECRET='.str_repeat('c', 64), $generatedEnv);
        $this->assertFileExists($renderedConfig);
        $this->assertFileExists($renderedGrafanaDatasources);
        $this->assertFileExists($grafanaPasswordFile);
        $this->assertStringNotContainsString(' up -d', @file_get_contents($dockerLog) ?: '');
    }

    public function test_obs_apply_fails_before_compose_when_prometheus_runtime_config_cannot_be_rendered(): void
    {
        $tempDir = $this->makeTempDir();
        $dockerLog = $tempDir.'/docker.log';
        $fakeBin = $this->makeFakeDockerBin($tempDir, $dockerLog);
        $grafanaPasswordFile = $tempDir.'/grafana-admin-secret';
        file_put_contents($grafanaPasswordFile, $this->fixtureGrafanaAdminSecretContents());
        file_put_contents($tempDir.'/compose.obs.yml', "services: {}\n");
        file_put_contents($tempDir.'/rendered-blocker', 'not-a-directory');

        $process = $this->runScript(
            [$this->repoRoot.'/ops/bootstrap/bootstrap-obs.sh', 'apply'],
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_COMPOSE_OBS_FILE' => $tempDir.'/compose.obs.yml',
                'BT_STATE_DIR' => $tempDir.'/state',
                'BT_RUNTIME_DIR' => $tempDir.'/state/runtime',
                'BT_OBS_GENERATED_ENV_FILE' => $tempDir.'/state/runtime/obs.generated.env',
                'BT_OBS_GENERATED_AUDIT_FILE' => $tempDir.'/state/runtime/obs.generated-secrets.jsonl',
                'BT_OBS_RENDERED_DIR' => $tempDir.'/rendered-blocker',
                'BT_GRAFANA_ADMIN_SECRET_FILE' => $grafanaPasswordFile,
                'MONITORING_ADMIN_USERNAME' => 'admin',
                'MONITORING_PASSWORD_HASH' => password_hash($this->fixturePlainCredential('monitoring'), PASSWORD_BCRYPT),
                'SESSION_SECRET' => str_repeat('b', 64),
                'GRAFANA_ADMIN_SECRET_FILE' => $grafanaPasswordFile,
                'PROMETHEUS_PASSWORD_HASH' => password_hash($this->fixturePlainCredential('prometheus'), PASSWORD_BCRYPT),
            ],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('"check_id":"obs.bootstrap.required_env"', $combinedOutput);
        $this->assertStringContainsString('"status":"FAIL"', $combinedOutput);
        $this->assertStringNotContainsString(' up -d', @file_get_contents($dockerLog) ?: '');
    }

    public function test_obs_compose_requires_explicit_prometheus_runtime_web_config_path(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.obs.yml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('${PROMETHEUS_WEB_CONFIG_FILE:?Set PROMETHEUS_WEB_CONFIG_FILE before obs apply}', $contents);
        $this->assertStringNotContainsString('${PROMETHEUS_WEB_CONFIG_FILE:-./docker/prometheus/web-config.yml}', $contents);
    }

    public function test_obs_compose_requires_explicit_grafana_runtime_datasource_config_path(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.obs.yml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('${GRAFANA_DATASOURCES_FILE:?Set GRAFANA_DATASOURCES_FILE before obs apply}:/etc/grafana/provisioning/datasources/datasources.yaml:ro', $contents);
        $this->assertStringNotContainsString('./docker/grafana/provisioning/datasources/datasources.yaml:/etc/grafana/provisioning/datasources/datasources.yaml:ro', $contents);
    }

    public function test_obs_compose_grafana_joins_app_plane_with_explicit_postgres_datasource_env(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.obs.yml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('GRAFANA_POSTGRES_URL: ${GRAFANA_POSTGRES_URL:-postgres:5432}', $contents);
        $this->assertStringContainsString('GRAFANA_POSTGRES_DATABASE: ${DB_DATABASE:?Set DB_DATABASE before obs apply}', $contents);
        $this->assertStringContainsString('GRAFANA_POSTGRES_USER: ${DB_USERNAME:?Set DB_USERNAME before obs apply}', $contents);
        $this->assertStringContainsString('GRAFANA_POSTGRES_SECRET: ${GRAFANA_POSTGRES_SECRET:?Set GRAFANA_POSTGRES_SECRET before obs apply}', $contents);
        $this->assertStringContainsString('GRAFANA_POSTGRES_SSLMODE: ${GRAFANA_POSTGRES_SSLMODE:-prefer}', $contents);
        $this->assertStringNotContainsString('GRAFANA_POSTGRES_PASSWORD:', $contents);
        $this->assertStringNotContainsString('DB_PASSWORD', $contents);
        $this->assertMatchesRegularExpression(
            "/^  grafana:\\n(?:(?:    |      ).*\\n)*?    networks:\\n      - obs-plane\\n      - app-plane\\n/m",
            $contents
        );
    }

    public function test_app_and_obs_compose_allow_an_explicit_app_plane_network_override(): void
    {
        $appContents = file_get_contents($this->repoRoot.'/compose.app.yml');
        $obsContents = file_get_contents($this->repoRoot.'/compose.obs.yml');

        $this->assertIsString($appContents);
        $this->assertIsString($obsContents);
        $this->assertStringContainsString("  app-plane:\n    external: true\n", $appContents);
        $this->assertStringContainsString("  app-plane:\n    external: true\n", $obsContents);
        $this->assertStringContainsString('name: "${BT_APP_PLANE_NETWORK_NAME:-${COMPOSE_PROJECT_NAME:-jobs-borads}_app-plane}"', $appContents);
        $this->assertStringContainsString('name: "${BT_APP_PLANE_NETWORK_NAME:-${COMPOSE_PROJECT_NAME:-jobs-borads}_app-plane}"', $obsContents);
    }

    public function test_obs_compose_initializes_grafana_volume_permissions_before_starting_grafana(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.obs.yml');

        $this->assertIsString($contents);
        $this->assertStringContainsString("  grafana-data-init:\n", $contents);
        $this->assertStringContainsString('image: alpine:3.20', $contents);
        $this->assertStringContainsString('user: "0:0"', $contents);
        $this->assertStringContainsString('mkdir -p /var/lib/grafana/plugins && chown -R 472:0 /var/lib/grafana', $contents);
        $this->assertStringContainsString("network_mode: none\n", $contents);
        $this->assertStringContainsString("restart: \"no\"\n", $contents);
        $this->assertStringContainsString("      grafana-data-init:\n        condition: service_completed_successfully", $contents);
    }

    public function test_obs_compose_initializes_auth_service_log_volume_permissions_before_starting_auth_service(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.obs.yml');

        $this->assertIsString($contents);
        $this->assertStringContainsString("  auth-service-logs-init:\n", $contents);
        $this->assertStringContainsString('image: jobs-borads-auth-service', $contents);
        $this->assertStringContainsString('user: "0:0"', $contents);
        $this->assertStringContainsString('mkdir -p /var/log/auth-service && chown -R 100:101 /var/log/auth-service', $contents);
        $this->assertStringContainsString("      auth-service-logs-init:\n        condition: service_completed_successfully", $contents);
    }

    public function test_app_compose_uses_an_explicit_front_proxy_allowlist_for_https_aware_urls(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.app.yml');
        $commonContents = file_get_contents($this->repoRoot.'/ops/lib/common.sh');

        $this->assertIsString($contents);
        $this->assertIsString($commonContents);
        $this->assertStringContainsString('TRUSTED_PROXIES: ${TRUSTED_PROXIES:-172.29.0.20}', $contents);
        $this->assertStringContainsString('TRUSTED_PROXY_HEADERS: ${TRUSTED_PROXY_HEADERS:-x_forwarded}', $contents);
        $this->assertStringContainsString('ipv4_address: 172.29.0.20', $contents);
        $this->assertStringContainsString('BT_APP_PLANE_SUBNET:-172.29.0.0/24', $commonContents);
    }

    public function test_app_compose_requires_an_explicit_honeypot_source_artifact_path(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.app.yml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('${BT_HONEYPOT_SOURCE:?Set BT_HONEYPOT_SOURCE before app apply}:/etc/nginx/includes/blue-team-honeypot.conf:ro', $contents);
        $this->assertStringNotContainsString('/opt/blue-team/nginx/includes/blue-team-honeypot.conf:/etc/nginx/includes/blue-team-honeypot.conf:ro', $contents);
    }

    public function test_app_compose_keeps_crowdsec_image_bootstrap_and_healthchecks_appsec_readiness(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.app.yml');
        $appsecConfig = file_get_contents($this->repoRoot.'/docker/crowdsec/appsec-configs/appsec-default.yaml');

        $this->assertIsString($contents);
        $this->assertIsString($appsecConfig);
        $this->assertStringNotContainsString('entrypoint: ["/entrypoint.sh"]', $contents);
        $this->assertStringNotContainsString('./docker/crowdsec/entrypoint.sh:/entrypoint.sh:ro', $contents);
        $this->assertStringContainsString('./docker/crowdsec/appsec-configs/appsec-default.yaml:/etc/crowdsec/appsec-configs/appsec-default.yaml:ro', $contents);
        $this->assertStringContainsString('COLLECTIONS: "${CROWDSEC_REQUIRED_APPSEC_COLLECTIONS:-crowdsecurity/appsec-virtual-patching}"', $contents);
        $this->assertStringContainsString('APPSEC_CONFIGS: "${CROWDSEC_REQUIRED_APPSEC_CONFIG:-crowdsecurity/appsec-default}"', $contents);
        $this->assertStringContainsString('cscli appsec-configs list -c /etc/crowdsec/config.yaml', $contents);
        $this->assertStringContainsString('grep -Fq \"${CROWDSEC_REQUIRED_APPSEC_CONFIG:-crowdsecurity/appsec-default}\"', $contents);
        $this->assertStringContainsString("cscli appsec-rules list -c /etc/crowdsec/config.yaml", $contents);
        $this->assertStringContainsString("grep -Fq 'crowdsecurity/vpatch-'", $contents);
        $this->assertStringContainsString('name: crowdsecurity/appsec-default', $appsecConfig);
        $this->assertStringContainsString('crowdsecurity/appsec-generic-test', $appsecConfig);
        $this->assertStringNotContainsString('crowdsecurity/experimental-*', $appsecConfig);
        $this->assertStringNotContainsString('crowdsecurity/generic-*', $appsecConfig);
    }

    public function test_app_compose_waits_for_crowdsec_key_initialization_before_starting_nginx(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.app.yml');

        $this->assertIsString($contents);
        $this->assertMatchesRegularExpression(
            "/^  nginx:\\n(?:(?:    |      ).*\\n)*?    depends_on:\\n      laravel\\.test:\\n        condition: service_started\\n      crowdsec-key-init:\\n        condition: service_completed_successfully\\n/m",
            $contents
        );
    }

    public function test_blue_team_vm_common_defaults_keep_the_host_managed_honeypot_source_path(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/lib/common.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString(': "${BT_HONEYPOT_SOURCE:=/opt/blue-team/nginx/includes/blue-team-honeypot.conf}"', $contents);
    }

    public function test_nginx_conf_maps_default_honeypot_probe_names_for_decoy_logs(): void
    {
        $contents = file_get_contents($this->repoRoot.'/docker/nginx/nginx.conf');

        $this->assertIsString($contents);
        $this->assertStringContainsString('map $uri $blue_team_trap_name {', $contents);
        $this->assertStringContainsString('/.env env_probe;', $contents);
        $this->assertStringContainsString('/.git/config git_probe;', $contents);
        $this->assertStringContainsString('/phpmyadmin phpmyadmin_probe;', $contents);
        $this->assertStringContainsString('/wp-login.php wp_probe;', $contents);
        $this->assertStringContainsString('/admin-old admin_old_probe;', $contents);
    }

    public function test_nginx_crowdsec_recovery_can_render_bouncer_config_after_key_arrives(): void
    {
        $contents = file_get_contents($this->repoRoot.'/docker/nginx/nginx.conf');

        $this->assertIsString($contents);
        $this->assertStringContainsString('local template_path = "/etc/nginx/crowdsec-bouncer.conf.template"', $contents);
        $this->assertStringContainsString('_G.bt_crowdsec_render_config = function()', $contents);
        $this->assertStringContainsString('${CROWDSEC_BOUNCER_API_KEY}', $contents);
        $this->assertStringContainsString('rendered bouncer config from template', $contents);
        $this->assertStringNotContainsString('local retry_max_attempts = 12', $contents);
        $this->assertStringNotContainsString('if recovery_attempts >= retry_max_attempts then', $contents);
    }

    public function test_obs_promtail_scrapes_auth_service_structured_logs(): void
    {
        $contents = file_get_contents($this->repoRoot.'/docker/promtail/config.yaml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('- job_name: auth-service', $contents);
        $this->assertStringContainsString('job: auth-service', $contents);
        $this->assertStringContainsString('app: auth-service', $contents);
        $this->assertStringContainsString('__path__: /var/log/auth-service/*.log', $contents);
    }

    public function test_obs_grafana_provisions_auth_service_audit_dashboard(): void
    {
        $contents = file_get_contents($this->repoRoot.'/docker/grafana/provisioning/dashboards/auth-service-audit.json');

        $this->assertIsString($contents);
        $this->assertStringContainsString('"uid": "auth-service-audit"', $contents);
        $this->assertStringContainsString('audit.canonical_mirror.dropped', $contents);
        $this->assertStringContainsString('{app=\\"auth-service\\"}', $contents);
        $this->assertStringContainsString('audit\\\\.auth\\\\..*', $contents);
    }

    public function test_obs_grafana_provisions_stable_prometheus_and_loki_datasource_uids(): void
    {
        $contents = file_get_contents($this->repoRoot.'/docker/grafana/provisioning/datasources/datasources.yaml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('name: Prometheus', $contents);
        $this->assertStringContainsString('uid: PBFA97CFB590B2093', $contents);
        $this->assertStringContainsString('type: prometheus', $contents);
        $this->assertStringContainsString('name: Loki', $contents);
        $this->assertStringContainsString('uid: P8E80F9AEF21F6940', $contents);
        $this->assertStringContainsString('type: loki', $contents);
        $this->assertStringContainsString('url: http://loki:3100', $contents);
    }

    public function test_obs_grafana_provisions_stable_postgres_datasource_uid(): void
    {
        $contents = file_get_contents($this->repoRoot.'/docker/grafana/provisioning/datasources/datasources.yaml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('name: Postgres', $contents);
        $this->assertStringContainsString('uid: P8E949C5F1FC6F134', $contents);
        $this->assertStringContainsString('type: postgres', $contents);
        $this->assertStringContainsString('url: $GRAFANA_POSTGRES_URL', $contents);
        $this->assertStringContainsString('user: $GRAFANA_POSTGRES_USER', $contents);
        $this->assertStringContainsString('password: $GRAFANA_POSTGRES_SECRET', $contents);
        $this->assertStringNotContainsString('GRAFANA_POSTGRES_PASSWORD', $contents);
        $this->assertStringContainsString('database: $GRAFANA_POSTGRES_DATABASE', $contents);
        $this->assertStringContainsString('sslmode: $GRAFANA_POSTGRES_SSLMODE', $contents);
    }

    public function test_obs_grafana_datasource_template_keeps_stable_uids_without_hardcoded_stale_alias_cleanup(): void
    {
        $contents = file_get_contents($this->repoRoot.'/docker/grafana/provisioning/datasources/datasources.yaml');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('deleteDatasources:', $contents);
        $this->assertStringNotContainsString('prune: true', $contents);
        $this->assertStringNotContainsString('JobsBoards-Postgres', $contents);
    }

    public function test_obs_prepare_renders_grafana_runtime_datasource_config_with_detected_uid_aliases(): void
    {
        $tempDir = $this->makeTempDir();
        $dockerLog = $tempDir.'/docker.log';
        $fakeBin = $tempDir.'/fake-bin';
        mkdir($fakeBin, 0777, true);
        file_put_contents($tempDir.'/compose.obs.yml', "services: {}\n");

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "-f" && "\${4:-}" == "config" ]]; then
  cat <<'JSON'
{"name":"jobs-borads","services":{"grafana":{"volumes":[{"type":"volume","source":"grafana-data","target":"/var/lib/grafana"}]}},"volumes":{"grafana-data":{"name":"jobs-borads_grafana-data"}}}
JSON
  exit 0
fi
if [[ "\${1:-}" == "volume" && "\${2:-}" == "inspect" && "\${3:-}" == "jobs-borads_grafana-data" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "run" ]]; then
  cat <<'JSON'
[{"orgId":1,"name":"JobsBoards-Postgres","uid":"P8E949C5F1FC6F134"}]
JSON
  exit 0
fi
exit 0
BASH);

        $process = $this->runScript(
            [$this->repoRoot.'/ops/bootstrap/bootstrap-obs.sh', 'prepare'],
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_COMPOSE_OBS_FILE' => $tempDir.'/compose.obs.yml',
                'BT_STATE_DIR' => $tempDir.'/state',
                'BT_RUNTIME_DIR' => $tempDir.'/state/runtime',
                'BT_OBS_GENERATED_ENV_FILE' => $tempDir.'/state/runtime/obs.generated.env',
                'BT_OBS_GENERATED_AUDIT_FILE' => $tempDir.'/state/runtime/obs.generated-secrets.jsonl',
                'BT_OBS_RENDERED_DIR' => $tempDir.'/state/rendered',
                'MONITORING_ADMIN_USERNAME' => 'admin',
                'MONITORING_PASSWORD' => $this->fixturePlainCredential('monitoring'),
                'GRAFANA_PASSWORD' => $this->fixturePlainCredential('grafana'),
                'PROMETHEUS_PASSWORD' => $this->fixturePlainCredential('prometheus'),
            ],
        );

        $renderedGrafanaDatasources = @file_get_contents($tempDir.'/state/rendered/grafana.datasources.yml') ?: '';
        $generatedEnv = @file_get_contents($tempDir.'/state/runtime/obs.generated.env') ?: '';

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('GRAFANA_DATASOURCES_FILE='.$tempDir.'/state/rendered/grafana.datasources.yml', $generatedEnv);
        $this->assertStringContainsString('deleteDatasources:', $renderedGrafanaDatasources);
        $this->assertStringContainsString('JobsBoards-Postgres', $renderedGrafanaDatasources);
        $this->assertStringContainsString('uid: P8E949C5F1FC6F134', $renderedGrafanaDatasources);
    }

    public function test_obs_apply_uses_a_detected_existing_app_plane_network_when_default_name_is_missing(): void
    {
        $tempDir = $this->makeTempDir();
        $dockerLog = $tempDir.'/docker.log';
        $dockerEnvLog = $tempDir.'/docker-env.log';
        $fakeBin = $tempDir.'/fake-bin';
        mkdir($fakeBin, 0777, true);
        file_put_contents($tempDir.'/compose.obs.yml', "services: {}\n");

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "-f" && "\${4:-}" == "config" ]]; then
  cat <<'JSON'
{"name":"jobs-borads","services":{"grafana":{"volumes":[{"type":"volume","source":"grafana-data","target":"/var/lib/grafana"}]}},"volumes":{"grafana-data":{"name":"jobs-borads_grafana-data"}}}
JSON
  exit 0
fi
if [[ "\${1:-}" == "volume" && "\${2:-}" == "inspect" && "\${3:-}" == "jobs-borads_grafana-data" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "run" ]]; then
  printf '[]'
  exit 0
fi
if [[ "\${1:-}" == "network" && "\${2:-}" == "inspect" && "\${3:-}" == "jobs-borads_app-plane" ]]; then
  exit 1
fi
if [[ "\${1:-}" == "network" && "\${2:-}" == "inspect" && "\${3:-}" == "canonical-audit-visibility_app-plane" ]]; then
  cat <<'JSON'
[{"IPAM":{"Config":[{"Subnet":"172.29.0.0/24"}]}}]
JSON
  exit 0
fi
if [[ "\${1:-}" == "network" && "\${2:-}" == "inspect" && "\${3:-}" == "-f" && "\${5:-}" == "canonical-audit-visibility_app-plane" ]]; then
  printf '172.29.0.0/24\n'
  exit 0
fi
if [[ "\${1:-}" == "inspect" && "\${2:-}" == "-f" && "\${4:-}" == "jobs-boards-nginx" ]]; then
  printf 'canonical-audit-visibility_app-plane\n'
  exit 0
fi
if [[ "\${1:-}" == "compose" && "\${4:-}" == "up" && "\${5:-}" == "-d" ]]; then
  printf 'BT_APP_PLANE_NETWORK_NAME=%s\n' "\${BT_APP_PLANE_NETWORK_NAME:-}" >> "{$dockerEnvLog}"
  exit 23
fi
exit 0
BASH);

        $process = $this->runScript(
            [$this->repoRoot.'/ops/bootstrap/bootstrap-obs.sh', 'apply'],
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_COMPOSE_OBS_FILE' => $tempDir.'/compose.obs.yml',
                'BT_STATE_DIR' => $tempDir.'/state',
                'BT_RUNTIME_DIR' => $tempDir.'/state/runtime',
                'BT_OBS_GENERATED_ENV_FILE' => $tempDir.'/state/runtime/obs.generated.env',
                'BT_OBS_GENERATED_AUDIT_FILE' => $tempDir.'/state/runtime/obs.generated-secrets.jsonl',
                'BT_OBS_RENDERED_DIR' => $tempDir.'/state/rendered',
                'MONITORING_ADMIN_USERNAME' => 'admin',
                'MONITORING_PASSWORD' => $this->fixturePlainCredential('monitoring'),
                'GRAFANA_PASSWORD' => $this->fixturePlainCredential('grafana'),
                'PROMETHEUS_PASSWORD' => $this->fixturePlainCredential('prometheus'),
            ],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerEnvOutput = (string) @file_get_contents($dockerEnvLog);

        $this->assertSame(23, $process->getExitCode());
        $this->assertStringContainsString('"check_id":"obs.bootstrap.app_plane_network"', $combinedOutput);
        $this->assertStringContainsString('"status":"PASS"', $combinedOutput);
        $this->assertStringContainsString('BT_APP_PLANE_NETWORK_NAME=canonical-audit-visibility_app-plane', $dockerEnvOutput);
    }

    public function test_obs_apply_creates_the_default_app_plane_network_when_detected_candidates_are_incompatible(): void
    {
        $tempDir = $this->makeTempDir();
        $dockerLog = $tempDir.'/docker.log';
        $dockerEnvLog = $tempDir.'/docker-env.log';
        $fakeBin = $tempDir.'/fake-bin';
        mkdir($fakeBin, 0777, true);
        file_put_contents($tempDir.'/compose.obs.yml', "services: {}\n");

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "-f" && "\${4:-}" == "config" ]]; then
  cat <<'JSON'
{"name":"jobs-borads","services":{"grafana":{"volumes":[{"type":"volume","source":"grafana-data","target":"/var/lib/grafana"}]}},"volumes":{"grafana-data":{"name":"jobs-borads_grafana-data"}}}
JSON
  exit 0
fi
if [[ "\${1:-}" == "volume" && "\${2:-}" == "inspect" && "\${3:-}" == "jobs-borads_grafana-data" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "run" ]]; then
  printf '[]'
  exit 0
fi
if [[ "\${1:-}" == "network" && "\${2:-}" == "inspect" && "\${3:-}" == "jobs-borads_app-plane" ]]; then
  exit 1
fi
if [[ "\${1:-}" == "network" && "\${2:-}" == "inspect" && "\${3:-}" == "canonical-audit-visibility_app-plane" ]]; then
  cat <<'JSON'
[{"IPAM":{"Config":[{"Subnet":"192.168.147.0/24"}]}}]
JSON
  exit 0
fi
if [[ "\${1:-}" == "network" && "\${2:-}" == "inspect" && "\${3:-}" == "-f" && "\${5:-}" == "canonical-audit-visibility_app-plane" ]]; then
  printf '192.168.147.0/24\n'
  exit 0
fi
if [[ "\${1:-}" == "inspect" && "\${2:-}" == "-f" && "\${4:-}" == "jobs-boards-nginx" ]]; then
  printf 'canonical-audit-visibility_app-plane\n'
  exit 0
fi
if [[ "\${1:-}" == "network" && "\${2:-}" == "create" && "\${3:-}" == "--driver" && "\${4:-}" == "bridge" && "\${5:-}" == "--subnet" && "\${6:-}" == "172.29.0.0/24" && "\${7:-}" == "jobs-borads_app-plane" ]]; then
  printf 'jobs-borads_app-plane\n'
  exit 0
fi
if [[ "\${1:-}" == "compose" && "\${4:-}" == "up" && "\${5:-}" == "-d" ]]; then
  printf 'BT_APP_PLANE_NETWORK_NAME=%s\n' "\${BT_APP_PLANE_NETWORK_NAME:-}" >> "{$dockerEnvLog}"
  exit 23
fi
exit 0
BASH);

        $process = $this->runScript(
            [$this->repoRoot.'/ops/bootstrap/bootstrap-obs.sh', 'apply'],
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_COMPOSE_OBS_FILE' => $tempDir.'/compose.obs.yml',
                'BT_STATE_DIR' => $tempDir.'/state',
                'BT_RUNTIME_DIR' => $tempDir.'/state/runtime',
                'BT_OBS_GENERATED_ENV_FILE' => $tempDir.'/state/runtime/obs.generated.env',
                'BT_OBS_GENERATED_AUDIT_FILE' => $tempDir.'/state/runtime/obs.generated-secrets.jsonl',
                'BT_OBS_RENDERED_DIR' => $tempDir.'/state/rendered',
                'MONITORING_ADMIN_USERNAME' => 'admin',
                'MONITORING_PASSWORD' => $this->fixturePlainCredential('monitoring'),
                'GRAFANA_PASSWORD' => $this->fixturePlainCredential('grafana'),
                'PROMETHEUS_PASSWORD' => $this->fixturePlainCredential('prometheus'),
            ],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerEnvOutput = (string) @file_get_contents($dockerEnvLog);
        $dockerCommands = (string) @file_get_contents($dockerLog);

        $this->assertSame(23, $process->getExitCode());
        $this->assertStringContainsString('"check_id":"obs.bootstrap.app_plane_network"', $combinedOutput);
        $this->assertStringContainsString('"status":"PASS"', $combinedOutput);
        $this->assertStringContainsString('network create --driver bridge --subnet 172.29.0.0/24 jobs-borads_app-plane', $dockerCommands);
        $this->assertStringContainsString('BT_APP_PLANE_NETWORK_NAME=jobs-borads_app-plane', $dockerEnvOutput);
    }

    public function test_obs_apply_fails_before_compose_when_configured_app_plane_network_has_an_incompatible_subnet(): void
    {
        $tempDir = $this->makeTempDir();
        $dockerLog = $tempDir.'/docker.log';
        $fakeBin = $tempDir.'/fake-bin';
        mkdir($fakeBin, 0777, true);
        file_put_contents($tempDir.'/compose.obs.yml', "services: {}\n");

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "-f" && "\${4:-}" == "config" ]]; then
  cat <<'JSON'
{"name":"jobs-borads","services":{"grafana":{"volumes":[{"type":"volume","source":"grafana-data","target":"/var/lib/grafana"}]}},"volumes":{"grafana-data":{"name":"jobs-borads_grafana-data"}}}
JSON
  exit 0
fi
if [[ "\${1:-}" == "volume" && "\${2:-}" == "inspect" && "\${3:-}" == "jobs-borads_grafana-data" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "run" ]]; then
  printf '[]'
  exit 0
fi
if [[ "\${1:-}" == "network" && "\${2:-}" == "inspect" && "\${3:-}" == "custom-app-plane" ]]; then
  cat <<'JSON'
[{"IPAM":{"Config":[{"Subnet":"192.168.147.0/24"}]}}]
JSON
  exit 0
fi
if [[ "\${1:-}" == "network" && "\${2:-}" == "inspect" && "\${3:-}" == "-f" && "\${5:-}" == "custom-app-plane" ]]; then
  printf '192.168.147.0/24\n'
  exit 0
fi
if [[ "\${1:-}" == "compose" && "\${4:-}" == "up" && "\${5:-}" == "-d" ]]; then
  exit 23
fi
exit 0
BASH);

        $process = $this->runScript(
            [$this->repoRoot.'/ops/bootstrap/bootstrap-obs.sh', 'apply'],
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_COMPOSE_OBS_FILE' => $tempDir.'/compose.obs.yml',
                'BT_STATE_DIR' => $tempDir.'/state',
                'BT_RUNTIME_DIR' => $tempDir.'/state/runtime',
                'BT_OBS_GENERATED_ENV_FILE' => $tempDir.'/state/runtime/obs.generated.env',
                'BT_OBS_GENERATED_AUDIT_FILE' => $tempDir.'/state/runtime/obs.generated-secrets.jsonl',
                'BT_OBS_RENDERED_DIR' => $tempDir.'/state/rendered',
                'BT_APP_PLANE_NETWORK_NAME' => 'custom-app-plane',
                'MONITORING_ADMIN_USERNAME' => 'admin',
                'MONITORING_PASSWORD' => $this->fixturePlainCredential('monitoring'),
                'GRAFANA_PASSWORD' => $this->fixturePlainCredential('grafana'),
                'PROMETHEUS_PASSWORD' => $this->fixturePlainCredential('prometheus'),
            ],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('"check_id":"obs.bootstrap.app_plane_network"', $combinedOutput);
        $this->assertStringContainsString('"status":"FAIL"', $combinedOutput);
        $this->assertStringContainsString('does not match required app-plane subnet 172.29.0.0/24', $combinedOutput);
        $this->assertStringNotContainsString(' up -d', (string) @file_get_contents($dockerLog));
    }

    public function test_app_compose_up_creates_the_default_external_app_plane_network_before_compose_up(): void
    {
        $tempDir = $this->makeTempDir();
        $dockerLog = $tempDir.'/docker.log';
        $dockerEnvLog = $tempDir.'/docker-env.log';
        $fakeBin = $tempDir.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        file_put_contents($tempDir.'/compose.app.yml', "services: {}\n");

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "network" && "\${2:-}" == "inspect" && "\${3:-}" == "jobs-borads_app-plane" ]]; then
  exit 1
fi
if [[ "\${1:-}" == "inspect" && "\${2:-}" == "-f" ]]; then
  exit 1
fi
if [[ "\${1:-}" == "network" && "\${2:-}" == "ls" ]]; then
  printf 'bridge\n'
  exit 0
fi
if [[ "\${1:-}" == "network" && "\${2:-}" == "create" && "\${3:-}" == "--driver" && "\${4:-}" == "bridge" && "\${5:-}" == "--subnet" && "\${6:-}" == "172.29.0.0/24" && "\${7:-}" == "jobs-borads_app-plane" ]]; then
  printf 'jobs-borads_app-plane\n'
  exit 0
fi
if [[ "\${1:-}" == "compose" && "\${4:-}" == "up" && "\${5:-}" == "-d" ]]; then
  printf 'BT_APP_PLANE_NETWORK_NAME=%s\n' "\${BT_APP_PLANE_NETWORK_NAME:-}" >> "{$dockerEnvLog}"
  exit 0
fi
exit 0
BASH);

        $process = $this->runScript(
            [$this->repoRoot.'/ops/app/05-compose-up.sh'],
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_COMPOSE_APP_FILE' => $tempDir.'/compose.app.yml',
                'BT_STATE_DIR' => $tempDir.'/state',
                'BT_RUNTIME_DIR' => $tempDir.'/state/runtime',
            ],
        );

        $dockerCommands = (string) @file_get_contents($dockerLog);
        $dockerEnvOutput = (string) @file_get_contents($dockerEnvLog);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('network create --driver bridge --subnet 172.29.0.0/24 jobs-borads_app-plane', $dockerCommands);
        $this->assertStringContainsString('compose -f '.$tempDir.'/compose.app.yml up -d', $dockerCommands);
        $this->assertStringContainsString('BT_APP_PLANE_NETWORK_NAME=jobs-borads_app-plane', $dockerEnvOutput);
    }

    public function test_common_bt_compose_exports_obs_generated_env_before_running_docker_compose(): void
    {
        $tempRoot = $this->makeTempDir();
        $dockerEnvLog = $tempRoot.'/docker-env.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/lib', 0777, true);
        mkdir($tempRoot.'/state/runtime', 0777, true);

        $this->writeExecutable(
            $tempRoot.'/ops/lib/common.sh',
            (string) file_get_contents($this->repoRoot.'/ops/lib/common.sh'),
        );

        file_put_contents(
            $tempRoot.'/state/runtime/obs.generated.env',
            "PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/state/rendered/prometheus.web-config.yml\n".
            "GRAFANA_DATASOURCES_FILE={$tempRoot}/state/rendered/grafana.datasources.yml\n".
            "GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/state/runtime/grafana-admin-secret\n"
        );

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
if [[ "\${1:-}" == "compose" ]]; then
  printf 'PROMETHEUS_WEB_CONFIG_FILE=%s\n' "\${PROMETHEUS_WEB_CONFIG_FILE:-}" >> "{$dockerEnvLog}"
  printf 'GRAFANA_DATASOURCES_FILE=%s\n' "\${GRAFANA_DATASOURCES_FILE:-}" >> "{$dockerEnvLog}"
  printf 'GRAFANA_ADMIN_SECRET_FILE=%s\n' "\${GRAFANA_ADMIN_SECRET_FILE:-}" >> "{$dockerEnvLog}"
  exit 7
fi
exit 0
BASH);

        $process = new Process(
            ['bash', '-c', 'source ./ops/lib/common.sh && bt_compose ./compose.obs.yml ps'],
            $tempRoot,
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_STATE_DIR' => $tempRoot.'/state',
                'BT_RUNTIME_DIR' => $tempRoot.'/state/runtime',
            ],
            null,
            20,
        );
        $process->run();

        $dockerEnvOutput = (string) @file_get_contents($dockerEnvLog);

        $this->assertSame(7, $process->getExitCode());
        $this->assertStringContainsString("PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/state/rendered/prometheus.web-config.yml", $dockerEnvOutput);
        $this->assertStringContainsString("GRAFANA_DATASOURCES_FILE={$tempRoot}/state/rendered/grafana.datasources.yml", $dockerEnvOutput);
        $this->assertStringContainsString("GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/state/runtime/grafana-admin-secret", $dockerEnvOutput);
    }

    public function test_common_bt_compose_does_not_override_explicit_runtime_env_exports(): void
    {
        $tempRoot = $this->makeTempDir();
        $dockerEnvLog = $tempRoot.'/docker-env.log';
        $fakeBin = $tempRoot.'/fake-bin';
        $explicitPrometheusPath = $tempRoot.'/explicit/prometheus.web-config.yml';
        $explicitGrafanaDatasourcePath = $tempRoot.'/explicit/grafana.datasources.yml';
        $explicitGrafanaPath = $tempRoot.'/explicit/grafana-admin-secret';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/lib', 0777, true);
        mkdir($tempRoot.'/state/runtime', 0777, true);

        $this->writeExecutable(
            $tempRoot.'/ops/lib/common.sh',
            (string) file_get_contents($this->repoRoot.'/ops/lib/common.sh'),
        );

        file_put_contents(
            $tempRoot.'/state/runtime/obs.generated.env',
            "PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/state/rendered/prometheus.web-config.yml\n".
            "GRAFANA_DATASOURCES_FILE={$tempRoot}/state/rendered/grafana.datasources.yml\n".
            "GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/state/runtime/grafana-admin-secret\n"
        );

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
if [[ "\${1:-}" == "compose" ]]; then
  printf 'PROMETHEUS_WEB_CONFIG_FILE=%s\n' "\${PROMETHEUS_WEB_CONFIG_FILE:-}" >> "{$dockerEnvLog}"
  printf 'GRAFANA_DATASOURCES_FILE=%s\n' "\${GRAFANA_DATASOURCES_FILE:-}" >> "{$dockerEnvLog}"
  printf 'GRAFANA_ADMIN_SECRET_FILE=%s\n' "\${GRAFANA_ADMIN_SECRET_FILE:-}" >> "{$dockerEnvLog}"
  exit 7
fi
exit 0
BASH);

        $process = new Process(
            ['bash', '-c', 'source ./ops/lib/common.sh && bt_compose ./compose.obs.yml ps'],
            $tempRoot,
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_STATE_DIR' => $tempRoot.'/state',
                'BT_RUNTIME_DIR' => $tempRoot.'/state/runtime',
                'PROMETHEUS_WEB_CONFIG_FILE' => $explicitPrometheusPath,
                'GRAFANA_DATASOURCES_FILE' => $explicitGrafanaDatasourcePath,
                'GRAFANA_ADMIN_SECRET_FILE' => $explicitGrafanaPath,
            ],
            null,
            20,
        );
        $process->run();

        $dockerEnvOutput = (string) @file_get_contents($dockerEnvLog);

        $this->assertSame(7, $process->getExitCode());
        $this->assertStringContainsString("PROMETHEUS_WEB_CONFIG_FILE={$explicitPrometheusPath}", $dockerEnvOutput);
        $this->assertStringContainsString("GRAFANA_DATASOURCES_FILE={$explicitGrafanaDatasourcePath}", $dockerEnvOutput);
        $this->assertStringContainsString("GRAFANA_ADMIN_SECRET_FILE={$explicitGrafanaPath}", $dockerEnvOutput);
    }

    public function test_common_bt_compose_prefers_generated_obs_runtime_values_over_repo_env_defaults(): void
    {
        $tempRoot = $this->makeTempDir();
        $dockerEnvLog = $tempRoot.'/docker-env.log';
        $fakeBin = $tempRoot.'/fake-bin';
        $repoEnvPrometheusPath = $tempRoot.'/repo-env/prometheus.web-config.yml';
        $repoEnvGrafanaDatasourcePath = $tempRoot.'/repo-env/grafana.datasources.yml';
        $repoEnvGrafanaPath = $tempRoot.'/repo-env/grafana-admin-secret';
        $generatedPrometheusPath = $tempRoot.'/state/rendered/prometheus.web-config.yml';
        $generatedGrafanaDatasourcePath = $tempRoot.'/state/rendered/grafana.datasources.yml';
        $generatedGrafanaPath = $tempRoot.'/state/runtime/grafana-admin-secret';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/lib', 0777, true);
        mkdir($tempRoot.'/state/runtime', 0777, true);

        $this->writeExecutable(
            $tempRoot.'/ops/lib/common.sh',
            (string) file_get_contents($this->repoRoot.'/ops/lib/common.sh'),
        );

        file_put_contents(
            $tempRoot.'/.env',
            "PROMETHEUS_WEB_CONFIG_FILE={$repoEnvPrometheusPath}\n".
            "GRAFANA_DATASOURCES_FILE={$repoEnvGrafanaDatasourcePath}\n".
            "GRAFANA_ADMIN_SECRET_FILE={$repoEnvGrafanaPath}\n"
        );

        file_put_contents(
            $tempRoot.'/state/runtime/obs.generated.env',
            "PROMETHEUS_WEB_CONFIG_FILE={$generatedPrometheusPath}\n".
            "GRAFANA_DATASOURCES_FILE={$generatedGrafanaDatasourcePath}\n".
            "GRAFANA_ADMIN_SECRET_FILE={$generatedGrafanaPath}\n"
        );

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
if [[ "\${1:-}" == "compose" ]]; then
  printf 'PROMETHEUS_WEB_CONFIG_FILE=%s\n' "\${PROMETHEUS_WEB_CONFIG_FILE:-}" >> "{$dockerEnvLog}"
  printf 'GRAFANA_DATASOURCES_FILE=%s\n' "\${GRAFANA_DATASOURCES_FILE:-}" >> "{$dockerEnvLog}"
  printf 'GRAFANA_ADMIN_SECRET_FILE=%s\n' "\${GRAFANA_ADMIN_SECRET_FILE:-}" >> "{$dockerEnvLog}"
  exit 7
fi
exit 0
BASH);

        $process = new Process(
            ['bash', '-c', 'source ./ops/lib/common.sh && bt_compose ./compose.obs.yml ps'],
            $tempRoot,
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_STATE_DIR' => $tempRoot.'/state',
                'BT_RUNTIME_DIR' => $tempRoot.'/state/runtime',
            ],
            null,
            20,
        );
        $process->run();

        $dockerEnvOutput = (string) @file_get_contents($dockerEnvLog);

        $this->assertSame(7, $process->getExitCode());
        $this->assertStringContainsString("PROMETHEUS_WEB_CONFIG_FILE={$generatedPrometheusPath}", $dockerEnvOutput);
        $this->assertStringContainsString("GRAFANA_DATASOURCES_FILE={$generatedGrafanaDatasourcePath}", $dockerEnvOutput);
        $this->assertStringContainsString("GRAFANA_ADMIN_SECRET_FILE={$generatedGrafanaPath}", $dockerEnvOutput);
        $this->assertStringNotContainsString("PROMETHEUS_WEB_CONFIG_FILE={$repoEnvPrometheusPath}", $dockerEnvOutput);
        $this->assertStringNotContainsString("GRAFANA_DATASOURCES_FILE={$repoEnvGrafanaDatasourcePath}", $dockerEnvOutput);
        $this->assertStringNotContainsString("GRAFANA_ADMIN_SECRET_FILE={$repoEnvGrafanaPath}", $dockerEnvOutput);
    }

    public function test_app_verify_extracts_crowdsec_mode_without_gnu_awk_case_insensitive_extensions(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/bootstrap/bootstrap-app.sh');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('IGNORECASE=1', $contents);
        $this->assertStringContainsString("grep -i '^x-crowdsec-mode:'", $contents);
    }

    public function test_app_verify_skips_host_port_exposure_contract_outside_linux_vm_runtime(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/bootstrap/bootstrap-app.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('app.host.local_ports', $contents);
        $this->assertStringContainsString('BT_STATUS_SKIPPED', $contents);
        $this->assertStringContainsString('Linux VM host exposure evidence is unavailable in this runtime.', $contents);
    }

    public function test_obs_bootstrap_avoids_bash4_lowercase_expansion_in_runtime_prepare_flow(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/bootstrap/bootstrap-obs.sh');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('${target_key,,}', $contents);
    }

    public function test_obs_apply_waits_for_grafana_and_auth_service_health_before_verifying(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/bootstrap/bootstrap-obs.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" auth-service healthy "${OBS_WAIT_TIMEOUT_SECONDS}" || true', $contents);
        $this->assertStringContainsString('bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" grafana healthy "${OBS_WAIT_TIMEOUT_SECONDS}" || true', $contents);
        $this->assertStringContainsString('bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" prometheus healthy "${OBS_WAIT_TIMEOUT_SECONDS}" || true', $contents);
    }

    public function test_app_bootstrap_avoids_bash4_mapfile_in_local_exposure_verifier(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/bootstrap/bootstrap-app.sh');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('mapfile -t listeners', $contents);
    }

    public function test_top_level_runner_emits_fail_summary_when_child_bootstrap_exits_before_plane_summary(): void
    {
        $tempRoot = $this->makeTempDir();

        $this->writeExecutable(
            $tempRoot.'/setup-blue-team-vm.sh',
            (string) file_get_contents($this->repoRoot.'/setup-blue-team-vm.sh'),
        );
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-host.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-app.sh', "#!/usr/bin/env bash\nexit 2\n");
        $this->writeExecutable(
            $tempRoot.'/ops/lib/common.sh',
            (string) file_get_contents($this->repoRoot.'/ops/lib/common.sh'),
        );
        file_put_contents($tempRoot.'/compose.app.yml', "services: {}\n");
        file_put_contents($tempRoot.'/compose.obs.yml', "services: {}\n");

        $process = $this->runScript(
            [$tempRoot.'/setup-blue-team-vm.sh', 'app'],
            [
                'BT_DRY_RUN' => '1',
            ],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $planeSummary = $this->findJsonRecord($combinedOutput, 'plane_summary', 'app');
        $overallSummary = $this->findJsonRecord($combinedOutput, 'overall_summary', 'overall');

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertSame('plane_summary', $planeSummary['record_type'] ?? null);
        $this->assertSame('app', $planeSummary['plane'] ?? null);
        $this->assertSame('FAIL', $planeSummary['status'] ?? null);
        $this->assertArrayHasKey('message', $planeSummary);
        $this->assertArrayHasKey('remediation_hint', $planeSummary);
        $this->assertStringContainsString('exited before emitting a structured plane summary', $planeSummary['message'] ?? '');
        $this->assertSame('overall_summary', $overallSummary['record_type'] ?? null);
        $this->assertSame('overall', $overallSummary['plane'] ?? null);
        $this->assertSame('FAIL', $overallSummary['status'] ?? null);
    }

    public function test_top_level_verify_preserves_host_failure_exit_when_child_verify_exits_before_plane_summary(): void
    {
        $tempRoot = $this->makeTempDir();
        $fakeBin = $tempRoot.'/fake-bin';
        mkdir($fakeBin, 0777, true);

        $this->writeExecutable(
            $tempRoot.'/setup-blue-team-vm.sh',
            (string) file_get_contents($this->repoRoot.'/setup-blue-team-vm.sh'),
        );
        $this->writeExecutable(
            $tempRoot.'/ops/lib/common.sh',
            (string) file_get_contents($this->repoRoot.'/ops/lib/common.sh'),
        );
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-host.sh', "#!/usr/bin/env bash\nexit 2\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-app.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', "#!/usr/bin/env bash\nexit 0\n");
        file_put_contents($tempRoot.'/compose.app.yml', "services: {}\n");
        file_put_contents($tempRoot.'/compose.obs.yml', "services: {}\n");
        $this->writeExecutable($fakeBin.'/docker', "#!/usr/bin/env bash\nexit 0\n");

        $process = new Process(
            [$tempRoot.'/setup-blue-team-vm.sh', 'verify'],
            $tempRoot,
            [
                'BT_DRY_RUN' => '1',
                'PATH' => $fakeBin.':'.getenv('PATH'),
            ],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $hostSummary = $this->findJsonRecord($combinedOutput, 'plane_summary', 'host');
        $overallSummary = $this->findJsonRecord($combinedOutput, 'overall_summary', 'overall');

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertSame('plane_summary', $hostSummary['record_type'] ?? null);
        $this->assertSame('host', $hostSummary['plane'] ?? null);
        $this->assertSame('FAIL', $hostSummary['status'] ?? null);
        $this->assertStringContainsString('Host bootstrap verify exited before emitting a structured plane summary.', $hostSummary['message'] ?? '');
        $this->assertStringNotContainsString('completed without emitting a structured plane summary', $hostSummary['message'] ?? '');
        $this->assertSame('overall_summary', $overallSummary['record_type'] ?? null);
        $this->assertSame('FAIL', $overallSummary['status'] ?? null);
    }

    public function test_top_level_verify_on_non_linux_runtime_points_to_child_plane_verifiers_for_local_evidence(): void
    {
        $tempRoot = $this->makeTempDir();
        $fakeBin = $tempRoot.'/fake-bin';
        mkdir($fakeBin, 0777, true);

        $this->writeExecutable(
            $tempRoot.'/setup-blue-team-vm.sh',
            (string) file_get_contents($this->repoRoot.'/setup-blue-team-vm.sh'),
        );
        $this->writeExecutable(
            $tempRoot.'/ops/lib/common.sh',
            (string) file_get_contents($this->repoRoot.'/ops/lib/common.sh'),
        );
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-host.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-app.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', "#!/usr/bin/env bash\nexit 0\n");
        file_put_contents($tempRoot.'/compose.app.yml', "services: {}\n");
        file_put_contents($tempRoot.'/compose.obs.yml', "services: {}\n");

        $this->writeExecutable($fakeBin.'/docker', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($fakeBin.'/git', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($fakeBin.'/uname', "#!/usr/bin/env bash\necho Darwin\n");

        $process = new Process(
            [$tempRoot.'/setup-blue-team-vm.sh', 'verify'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $kernelCheck = $this->findJsonRecord($combinedOutput, 'check', 'overall', 'overall.runtime.kernel_read_only_support');

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertSame('FAIL', $kernelCheck['status'] ?? null);
        $this->assertStringContainsString('Use a Linux VM with kernel 5.12 or newer for top-level verify.', $kernelCheck['remediation_hint'] ?? '');
        $this->assertStringContainsString('./ops/bootstrap/bootstrap-app.sh verify', $kernelCheck['remediation_hint'] ?? '');
        $this->assertStringContainsString('./ops/bootstrap/bootstrap-obs.sh verify', $kernelCheck['remediation_hint'] ?? '');
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-shell-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
    }

    private function makeFakeDockerBin(string $tempDir, string $dockerLog): string
    {
        $binDir = $tempDir.'/fake-bin';
        mkdir($binDir, 0777, true);

        $script = <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" ]]; then
  exit 99
fi
exit 0
BASH;

        $this->writeExecutable($binDir.'/docker', $script);

        return $binDir;
    }

    private function fixturePlainCredential(string $scope): string
    {
        return "fixture-{$scope}-value";
    }

    private function fixtureGrafanaAdminSecretContents(): string
    {
        return "fixture-admin-file\n";
    }

    /**
     * @param array<string> $command
     * @param array<string, string> $env
     */
    private function runScript(array $command, array $env): Process
    {
        $process = new Process($command, $this->repoRoot, $env, null, 20);
        $process->run();

        return $process;
    }

    private function writeExecutable(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $contents);
        chmod($path, 0755);
    }

    /**
     * @return array<string, mixed>
     */
    private function findJsonRecord(string $output, string $recordType, string $plane, ?string $checkId = null): array
    {
        foreach (preg_split('/\R/', $output) as $line) {
            if ($line === '') {
                continue;
            }

            $record = json_decode($line, true);
            if (! is_array($record)) {
                continue;
            }

            if (($record['record_type'] ?? null) !== $recordType || ($record['plane'] ?? null) !== $plane) {
                continue;
            }

            if ($checkId !== null && ($record['check_id'] ?? null) !== $checkId) {
                continue;
            }

            if (($record['record_type'] ?? null) === $recordType && ($record['plane'] ?? null) === $plane) {
                return $record;
            }
        }

        $suffix = $checkId === null ? '' : " and check_id {$checkId}";
        $this->fail("Did not find {$recordType} record for plane {$plane}{$suffix}");
    }
}
