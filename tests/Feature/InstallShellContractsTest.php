<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class InstallShellContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_install_full_does_not_use_linux_only_netstat_flags_when_probing_ports(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $tempRoot.'/install.sh';
        $netstatLog = $tempRoot.'/netstat.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        copy($this->repoRoot.'/install.sh', $scriptPath);
        chmod($scriptPath, 0755);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml
GRAFANA_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret
EOF
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "compose" && "${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "${1:-}" == "exec" ]]; then
  exit 1
fi
if [[ "${1:-}" == "compose" && ( "${2:-}" == "build" || ( "${2:-}" == "-f" && "${4:-}" == "build" ) ) ]]; then
  exit 7
fi
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/jq', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/netstat', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$netstatLog}"
if [[ "\${1:-}" == "-tlnp" ]]; then
  echo "-tlnp: option requires an argument -- p" >&2
  exit 1
fi
exit 0
BASH);

        $process = new Process(
            [$scriptPath, 'full', 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertSame(7, $process->getExitCode());
        $this->assertStringContainsString('Building laravel.test image...', $combinedOutput);
        $this->assertStringNotContainsString('option requires an argument -- p', $combinedOutput);
        $this->assertStringNotContainsString('-tlnp', @file_get_contents($netstatLog) ?: '');
    }

    public function test_install_full_prepares_obs_runtime_artifacts_before_docker_compose_build(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $tempRoot.'/install.sh';
        $bootstrapLog = $tempRoot.'/bootstrap-obs.log';
        $dockerEnvLog = $tempRoot.'/docker-env.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        copy($this->repoRoot.'/install.sh', $scriptPath);
        chmod($scriptPath, 0755);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf 'action=%s\n' "\${1:-}" >> "{$bootstrapLog}"
printf 'BT_STATE_DIR=%s\n' "\${BT_STATE_DIR:-}" >> "{$bootstrapLog}"
printf 'BT_RUNTIME_DIR=%s\n' "\${BT_RUNTIME_DIR:-}" >> "{$bootstrapLog}"
printf 'BT_COMPOSE_OBS_FILE=%s\n' "\${BT_COMPOSE_OBS_FILE:-}" >> "{$bootstrapLog}"
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml
GRAFANA_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret
EOF
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" ]]; then
  exit 1
fi
if [[ "\${1:-}" == "compose" && ( "\${2:-}" == "build" || ( "\${2:-}" == "-f" && "\${4:-}" == "build" ) ) ]]; then
  printf 'PROMETHEUS_WEB_CONFIG_FILE=%s\n' "\${PROMETHEUS_WEB_CONFIG_FILE:-}" >> "{$dockerEnvLog}"
  printf 'GRAFANA_SECRET_FILE=%s\n' "\${GRAFANA_SECRET_FILE:-}" >> "{$dockerEnvLog}"
  exit 7
fi
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'full', 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->run();

        $bootstrapOutput = (string) @file_get_contents($bootstrapLog);
        $dockerEnvOutput = (string) @file_get_contents($dockerEnvLog);

        $this->assertSame(7, $process->getExitCode());
        $this->assertStringContainsString('action=prepare', $bootstrapOutput);
        $this->assertStringContainsString("BT_STATE_DIR={$tempRoot}/.blue-team-vm", $bootstrapOutput);
        $this->assertStringContainsString("BT_RUNTIME_DIR={$tempRoot}/.blue-team-vm/runtime", $bootstrapOutput);
        $this->assertStringContainsString("BT_COMPOSE_OBS_FILE={$tempRoot}/compose.obs.yml", $bootstrapOutput);
        $this->assertStringContainsString("PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml", $dockerEnvOutput);
        $this->assertStringContainsString("GRAFANA_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret", $dockerEnvOutput);
    }

    public function test_install_full_prefers_generated_obs_runtime_values_over_repo_env_defaults_during_compose_calls(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $tempRoot.'/install.sh';
        $dockerEnvLog = $tempRoot.'/docker-env.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        copy($this->repoRoot.'/install.sh', $scriptPath);
        chmod($scriptPath, 0755);

        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=8080
APP_SSL_PORT=8443
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/repo-env/prometheus.web-config.yml
GRAFANA_SECRET_FILE={$tempRoot}/repo-env/grafana-admin-secret
ENV);

        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml
GRAFANA_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret
EOF
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" ]]; then
  exit 1
fi
if [[ "\${1:-}" == "compose" && ( "\${2:-}" == "build" || ( "\${2:-}" == "-f" && "\${4:-}" == "build" ) ) ]]; then
  printf 'PROMETHEUS_WEB_CONFIG_FILE=%s\n' "\${PROMETHEUS_WEB_CONFIG_FILE:-}" >> "{$dockerEnvLog}"
  printf 'GRAFANA_SECRET_FILE=%s\n' "\${GRAFANA_SECRET_FILE:-}" >> "{$dockerEnvLog}"
  exit 7
fi
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'full', 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->run();

        $dockerEnvOutput = (string) @file_get_contents($dockerEnvLog);

        $this->assertSame(7, $process->getExitCode());
        $this->assertStringContainsString("PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml", $dockerEnvOutput);
        $this->assertStringContainsString("GRAFANA_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret", $dockerEnvOutput);
        $this->assertStringNotContainsString("PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/repo-env/prometheus.web-config.yml", $dockerEnvOutput);
        $this->assertStringNotContainsString("GRAFANA_SECRET_FILE={$tempRoot}/repo-env/grafana-admin-secret", $dockerEnvOutput);
    }

    public function test_install_full_exports_repo_local_honeypot_source_before_docker_compose_build(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $tempRoot.'/install.sh';
        $dockerEnvLog = $tempRoot.'/docker-env.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);
        mkdir($tempRoot.'/docker/nginx/includes', 0777, true);

        copy($this->repoRoot.'/install.sh', $scriptPath);
        chmod($scriptPath, 0755);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        file_put_contents($tempRoot.'/docker/nginx/includes/blue-team-honeypot.conf', "location = /.env { return 403; }\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml
GRAFANA_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret
EOF
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" ]]; then
  exit 1
fi
if [[ "\${1:-}" == "compose" && ( "\${2:-}" == "build" || ( "\${2:-}" == "-f" && "\${4:-}" == "build" ) ) ]]; then
  printf 'BT_HONEYPOT_SOURCE=%s\n' "\${BT_HONEYPOT_SOURCE:-}" >> "{$dockerEnvLog}"
  exit 7
fi
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'full', 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->run();

        $dockerEnvOutput = (string) @file_get_contents($dockerEnvLog);

        $this->assertSame(7, $process->getExitCode());
        $this->assertStringContainsString("BT_HONEYPOT_SOURCE={$tempRoot}/docker/nginx/includes/blue-team-honeypot.conf", $dockerEnvOutput);
    }

    public function test_install_full_uses_explicit_combined_compose_file_without_orphan_cleanup(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $tempRoot.'/install.sh';
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        copy($this->repoRoot.'/install.sh', $scriptPath);
        chmod($scriptPath, 0755);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml
GRAFANA_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret
EOF
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" ]]; then
  exit 1
fi
if [[ "\${1:-}" == "compose" && ( "\${2:-}" == "up" || "\${4:-}" == "up" ) ]]; then
  exit 7
fi
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'full', 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->run();

        $dockerOutput = (string) @file_get_contents($dockerLog);

        $this->assertSame(7, $process->getExitCode());
        $this->assertStringContainsString("compose -f {$tempRoot}/compose.yaml build laravel.test", $dockerOutput);
        $this->assertStringContainsString("compose -f {$tempRoot}/compose.yaml up -d", $dockerOutput);
        $this->assertStringNotContainsString('down --remove-orphans', $dockerOutput);
    }

    public function test_install_quick_restarts_the_app_container_without_combined_compose_interpolation(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $tempRoot.'/install.sh';
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);

        copy($this->repoRoot.'/install.sh', $scriptPath);
        chmod($scriptPath, 0755);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "compose" && ( "\${2:-}" == "restart" || ( "\${2:-}" == "-f" && "\${4:-}" == "restart" ) ) ]]; then
  exit 7
fi
if [[ "\${1:-}" == "restart" && "\${2:-}" == "jobs-boards-laravel.test" ]]; then
  exit 0
fi
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'quick', 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            "y\n",
            20,
        );
        $process->run();

        $dockerOutput = (string) @file_get_contents($dockerLog);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertStringContainsString('restart jobs-boards-laravel.test', $dockerOutput);
        $this->assertStringNotContainsString('compose -f', $dockerOutput);
        $this->assertStringNotContainsString('compose restart laravel.test', $dockerOutput);
    }

    public function test_combined_compose_requires_generated_obs_runtime_artifacts(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.yaml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('${MONITORING_ADMIN_USERNAME:?Set MONITORING_ADMIN_USERNAME before docker compose up}', $contents);
        $this->assertStringContainsString('${MONITORING_PASSWORD_HASH:?Set MONITORING_PASSWORD_HASH before docker compose up}', $contents);
        $this->assertStringContainsString('${SESSION_SECRET:?Set SESSION_SECRET before docker compose up}', $contents);
        $this->assertStringContainsString('${PROMETHEUS_PASSWORD_HASH:?Set PROMETHEUS_PASSWORD_HASH before docker compose up}', $contents);
        $this->assertStringContainsString('${PROMETHEUS_WEB_CONFIG_FILE:?Set PROMETHEUS_WEB_CONFIG_FILE before docker compose up}', $contents);
        $this->assertStringContainsString('${GRAFANA_SECRET_FILE:?Set GRAFANA_SECRET_FILE before docker compose up}', $contents);
        $this->assertStringContainsString('GF_SECURITY_ADMIN_PASSWORD__FILE: /run/secrets/grafana_admin_secret', $contents);
        $this->assertStringNotContainsString('${PROMETHEUS_WEB_CONFIG_FILE:-./docker/prometheus/web-config.yml}', $contents);
        $this->assertStringNotContainsString('GF_SECURITY_ADMIN_PASSWORD:', $contents);
    }

    public function test_combined_compose_uses_an_explicit_front_proxy_allowlist_for_https_aware_urls(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.yaml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('TRUSTED_PROXIES: ${TRUSTED_PROXIES:-172.30.0.20}', $contents);
        $this->assertStringContainsString('TRUSTED_PROXY_HEADERS: ${TRUSTED_PROXY_HEADERS:-x_forwarded}', $contents);
        $this->assertStringContainsString('ipv4_address: 172.30.0.20', $contents);
        $this->assertStringContainsString('subnet: 172.30.0.0/24', $contents);
    }

    public function test_combined_compose_requires_explicit_honeypot_source_and_frontdoor_healthcheck(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.yaml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('${BT_HONEYPOT_SOURCE:?Set BT_HONEYPOT_SOURCE before docker compose up}:/etc/nginx/includes/blue-team-honeypot.conf:ro', $contents);
        $this->assertStringContainsString('curl -kfsS https://127.0.0.1/up || exit 1', $contents);
        $this->assertStringNotContainsString('curl -sf http://laravel.test:80/up || exit 1', $contents);
    }

    public function test_combined_compose_mirrors_app_plane_crowdsec_serving_path_contract(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.yaml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('entrypoint: ["/entrypoint.sh"]', $contents);
        $this->assertStringContainsString('./docker/crowdsec/entrypoint.sh:/entrypoint.sh:ro', $contents);
        $this->assertStringContainsString('./docker/crowdsec/acquis.d/fp-trap.yaml:/etc/crowdsec/acquis.d/fp-trap.yaml:ro', $contents);
        $this->assertStringContainsString('CROWDSEC_REQUIRED_APPSEC_CONFIG: "${CROWDSEC_REQUIRED_APPSEC_CONFIG:-crowdsecurity/appsec-default}"', $contents);
        $this->assertStringContainsString('CROWDSEC_REQUIRED_APPSEC_COLLECTIONS: "${CROWDSEC_REQUIRED_APPSEC_COLLECTIONS:-crowdsecurity/appsec-virtual-patching}"', $contents);
        $this->assertStringContainsString('wget -qO- http://127.0.0.1:8080/health >/dev/null 2>&1 || exit 1', $contents);
        $this->assertStringNotContainsString('cscli version || exit 1', $contents);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-install-shell-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
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
}
