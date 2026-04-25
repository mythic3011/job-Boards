<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\ObsConfigContract;
use Tests\Support\ObsTestFixtures;

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
        $scriptPath = $this->installScriptFixture($tempRoot);
        $netstatLog = $tempRoot.'/netstat.log';
        $fakeBin = $tempRoot.'/fake-bin';

        if (! is_dir($fakeBin)) {
            mkdir($fakeBin, 0777, true);
        }

        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=8080
APP_SSL_PORT=8443
VITE_PORT=18173
FORWARD_DB_PORT=15432
FORWARD_REDIS_PORT=16379
ENV);
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', ObsTestFixtures::bootstrapObsGeneratedEnvScript($tempRoot.'/.blue-team-vm'));

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

    public function test_install_full_uses_plain_docker_exec_for_container_startup_probes(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        if (! is_dir($fakeBin)) {
            mkdir($fakeBin, 0777, true);
        }

        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=8080
APP_SSL_PORT=8443
VITE_PORT=18173
FORWARD_DB_PORT=15432
FORWARD_REDIS_PORT=16379
ENV);
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', ObsTestFixtures::bootstrapObsGeneratedEnvScript($tempRoot.'/.blue-team-vm'));

        $scriptContents = file_get_contents($scriptPath);
        $this->assertIsString($scriptContents);
        $patchedScript = (string) preg_replace(
            '/wait_for "Container starting" 30 docker exec "\\$\\{?CONTAINER\\}?" true/',
            'wait_for "Container starting" 1 docker exec "$CONTAINER" true',
            $scriptContents,
            1,
        );
        $this->assertNotSame($scriptContents, $patchedScript, 'Failed to patch wait_for timeout in install fixture.');
        file_put_contents(
            $scriptPath,
            $patchedScript,
        );
        chmod($scriptPath, 0755);

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
state_file="{$tempRoot}/container.started"
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "compose" && ( "\${2:-}" == "build" || ( "\${2:-}" == "-f" && "\${4:-}" == "build" ) ) ]]; then
  exit 0
fi
if [[ "\${1:-}" == "compose" && ( "\${2:-}" == "up" || ( "\${2:-}" == "-f" && "\${4:-}" == "up" ) ) ]]; then
  : > "\${state_file}"
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${2:-}" == "-T" ]]; then
  echo "unknown shorthand flag: 'T' in -T" >&2
  exit 125
fi
if [[ "\${1:-}" == "exec" ]]; then
  shift
  container="\${1:-}"
  shift || true
  if [[ ! -f "\${state_file}" ]]; then
    exit 1
  fi
  if [[ "\${1:-}" == "true" ]]; then
    exit 0
  fi
  if [[ "\${1:-}" == "test" && "\${2:-}" == "-f" ]]; then
    exit 0
  fi
  if [[ "\${1:-}" == "php" && "\${2:-}" == "artisan" && "\${3:-}" == "--version" ]]; then
    exit 0
  fi
  if [[ "\${1:-}" == "php" && "\${2:-}" == "artisan" && "\${3:-}" == "tinker" ]]; then
    exit 1
  fi
  if [[ "\${1:-}" == "php" && "\${2:-}" == "artisan" && "\${3:-}" == "optimize:clear" ]]; then
    exit 0
  fi
  if [[ "\${1:-}" == "npm" && "\${2:-}" == "install" ]]; then
    exit 0
  fi
  if [[ "\${1:-}" == "npm" && "\${2:-}" == "run" && "\${3:-}" == "build" ]]; then
    exit 0
  fi
  if [[ "\${1:-}" == "php" && "\${2:-}" == "artisan" && "\${3:-}" == "migrate" && "\${4:-}" == "--force" ]]; then
    exit 17
  fi
  exit 0
fi
if [[ "\${1:-}" == "restart" ]]; then
  exit 0
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

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerOutput = (string) @file_get_contents($dockerLog);

        $this->assertSame(17, $process->getExitCode(), $combinedOutput);
        $this->assertStringNotContainsString("unknown shorthand flag: 'T' in -T", $combinedOutput);
        $this->assertStringContainsString('exec jobs-boards-laravel.test true', $dockerOutput);
        $this->assertStringNotContainsString('exec -T jobs-boards-laravel.test true', $dockerOutput);
    }

    public function test_install_full_prepares_obs_runtime_artifacts_before_docker_compose_build(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $bootstrapLog = $tempRoot.'/bootstrap-obs.log';
        $dockerEnvLog = $tempRoot.'/docker-env.log';
        $fakeBin = $tempRoot.'/fake-bin';

        if (! is_dir($fakeBin)) {
            mkdir($fakeBin, 0777, true);
        }

        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=8080
APP_SSL_PORT=8443
VITE_PORT=18173
FORWARD_DB_PORT=15432
FORWARD_REDIS_PORT=16379
ENV);
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $generatedEnv = ObsConfigContract::generatedEnvContents($tempRoot.'/.blue-team-vm');
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf 'action=%s\n' "\${1:-}" >> "{$bootstrapLog}"
printf 'BT_STATE_DIR=%s\n' "\${BT_STATE_DIR:-}" >> "{$bootstrapLog}"
printf 'BT_RUNTIME_DIR=%s\n' "\${BT_RUNTIME_DIR:-}" >> "{$bootstrapLog}"
printf 'BT_COMPOSE_OBS_FILE=%s\n' "\${BT_COMPOSE_OBS_FILE:-}" >> "{$bootstrapLog}"
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
{$generatedEnv}
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
  printf 'GRAFANA_ADMIN_SECRET_FILE=%s\n' "\${GRAFANA_ADMIN_SECRET_FILE:-}" >> "{$dockerEnvLog}"
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
        $this->assertStringContainsString("GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret", $dockerEnvOutput);
    }

    public function test_install_full_keeps_repo_prometheus_override_but_preserves_generated_grafana_secret_path(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerEnvLog = $tempRoot.'/docker-env.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        if (! is_dir($tempRoot.'/ops/bootstrap')) {
            mkdir($tempRoot.'/ops/bootstrap', 0777, true);
        }

        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=8080
APP_SSL_PORT=8443
VITE_PORT=18173
FORWARD_DB_PORT=15432
FORWARD_REDIS_PORT=16379
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/repo-env/prometheus.web-config.yml
GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/repo-env/grafana-admin-secret
ENV);

        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', ObsTestFixtures::bootstrapObsGeneratedEnvScript($tempRoot.'/.blue-team-vm'));

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
  printf 'GRAFANA_ADMIN_SECRET_FILE=%s\n' "\${GRAFANA_ADMIN_SECRET_FILE:-}" >> "{$dockerEnvLog}"
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
        $this->assertStringContainsString("PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/repo-env/prometheus.web-config.yml", $dockerEnvOutput);
        $this->assertStringContainsString("GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret", $dockerEnvOutput);
        $this->assertStringNotContainsString("PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml", $dockerEnvOutput);
        $this->assertStringNotContainsString("GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/repo-env/grafana-admin-secret", $dockerEnvOutput);
    }

    public function test_install_full_exports_repo_local_honeypot_source_before_docker_compose_build(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerEnvLog = $tempRoot.'/docker-env.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);
        mkdir($tempRoot.'/docker/nginx/includes', 0777, true);

        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=8080
APP_SSL_PORT=8443
VITE_PORT=18173
FORWARD_DB_PORT=15432
FORWARD_REDIS_PORT=16379
ENV);
        file_put_contents($tempRoot.'/docker/nginx/includes/blue-team-honeypot.conf', "location = /.env { return 403; }\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml
GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret
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
            [
                'BT_HONEYPOT_SOURCE' => './docker/nginx/includes/blue-team-honeypot.conf',
                'PATH' => $fakeBin.':'.getenv('PATH'),
            ],
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
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=8080
APP_SSL_PORT=8443
VITE_PORT=18173
FORWARD_DB_PORT=15432
FORWARD_REDIS_PORT=16379
ENV);
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml
GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret
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

    public function test_install_full_probes_package_network_before_fallback_composer_install_when_vendor_is_missing(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml
GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret
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
if [[ "\${1:-}" == "exec" && "\${3:-}" == "true" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "test" && "\${4:-}" == "-f" && "\${5:-}" == "/var/www/html/vendor/autoload.php" ]]; then
  exit 1
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "curl" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "composer" && "\${4:-}" == "install" ]]; then
  exit 17
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
        $curlOffset = strpos($dockerOutput, 'exec jobs-boards-laravel.test curl ');
        $composerOffset = strpos($dockerOutput, 'exec jobs-boards-laravel.test composer install --no-interaction --prefer-dist');

        $this->assertSame(17, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertNotFalse($curlOffset, 'Expected install.sh to probe package-network reachability before composer repair.');
        $this->assertNotFalse($composerOffset, 'Expected install.sh to attempt composer repair when the package network is reachable.');
        $this->assertLessThan($composerOffset, $curlOffset, 'Package-network probe must happen before fallback composer install.');
    }

    public function test_install_full_treats_defined_ports_as_reserved_when_auto_assigning_conflicts(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        file_put_contents($tempRoot.'/.env', <<<ENV
PORT=3001
APP_PORT=80
APP_SSL_PORT=443
VITE_PORT=18173
FORWARD_DB_PORT=15432
FORWARD_REDIS_PORT=16379
ENV);

        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml
GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret
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
        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");
        $this->writeExecutable($fakeBin.'/ss', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
cat <<'EOF'
LISTEN 0 128 0.0.0.0:80 0.0.0.0:*
LISTEN 0 128 0.0.0.0:443 0.0.0.0:*
EOF
BASH);
        $this->writeExecutable($fakeBin.'/lsof', "#!/usr/bin/env bash\nset -euo pipefail\nexit 1\n");
        $this->writeExecutable($fakeBin.'/netstat', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'full', 'dev'],
            $tempRoot,
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_AUTO_ASSIGN_PORTS' => 'true',
            ],
            "Y\n",
            20,
        );
        $process->run();

        $envContents = file_get_contents($tempRoot.'/.env');

        $this->assertSame(7, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertIsString($envContents);
        $this->assertMatchesRegularExpression('/^PORT=3001$/m', $envContents);
        $this->assertSame(1, preg_match('/^APP_PORT=(\d+)$/m', $envContents, $appPortMatches));
        $this->assertSame(1, preg_match('/^APP_SSL_PORT=(\d+)$/m', $envContents, $sslPortMatches));
        $this->assertNotSame('3001', $appPortMatches[1]);
        $this->assertNotSame('3001', $sslPortMatches[1]);
        $this->assertNotSame($appPortMatches[1], $sslPortMatches[1]);
    }

    public function test_install_bootstrap_keeps_ports_already_owned_by_the_running_stack(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=80
APP_SSL_PORT=443
VITE_PORT=5173
FORWARD_DB_PORT=5432
FORWARD_REDIS_PORT=6379
ENV);

        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml
GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret
EOF
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "compose" && "${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "${1:-}" == "compose" && "${2:-}" == "-f" && "${4:-}" == "ps" && "${5:-}" == "-q" ]]; then
  if [[ "${6:-}" == "nginx" ]]; then
    echo "nginx-container"
  fi
  exit 0
fi
if [[ "${1:-}" == "inspect" && "${2:-}" == "-f" && "${4:-}" == "nginx-container" ]]; then
  if [[ "${3:-}" == "{{.State.Status}}" ]]; then
    echo "running"
    exit 0
  fi
  if [[ "${3:-}" == "{{json .HostConfig.PortBindings}}" ]]; then
    echo '{"80/tcp":[{"HostIp":"0.0.0.0","HostPort":"80"}],"443/tcp":[{"HostIp":"0.0.0.0","HostPort":"443"}]}'
    exit 0
  fi
  exit 1
fi
if [[ "${1:-}" == "port" && "${2:-}" == "nginx-container" ]]; then
  exit 0
fi
if [[ "${1:-}" == "exec" ]]; then
  exit 1
fi
exit 0
BASH);
        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");
        $this->writeExecutable($fakeBin.'/ss', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
cat <<'EOF'
LISTEN 0 128 0.0.0.0:80 0.0.0.0:*
LISTEN 0 128 0.0.0.0:443 0.0.0.0:*
EOF
BASH);
        $this->writeExecutable($fakeBin.'/lsof', "#!/usr/bin/env bash\nset -euo pipefail\nexit 1\n");
        $this->writeExecutable($fakeBin.'/netstat', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'bootstrap', 'dev'],
            $tempRoot,
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_AUTO_ASSIGN_PORTS' => 'true',
            ],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $envContents = file_get_contents($tempRoot.'/.env');

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertIsString($envContents);
        $this->assertMatchesRegularExpression('/^APP_PORT=80$/m', $envContents);
        $this->assertMatchesRegularExpression('/^APP_SSL_PORT=443$/m', $envContents);
        $this->assertStringNotContainsString('Port conflict detected:', $combinedOutput);
        $this->assertStringNotContainsString('APP_PORT: 80 ->', $combinedOutput);
        $this->assertStringNotContainsString('APP_SSL_PORT: 443 ->', $combinedOutput);
    }

    public function test_install_bootstrap_keeps_ports_when_current_stack_nginx_is_restarting(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=127.0.0.1:18080
APP_SSL_PORT=127.0.0.1:18443
VITE_PORT=5173
FORWARD_DB_PORT=5432
FORWARD_REDIS_PORT=6379
ENV);

        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml
GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret
EOF
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "compose" && "${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "${1:-}" == "compose" && "${2:-}" == "-f" && "${4:-}" == "ps" && "${5:-}" == "-q" ]]; then
  if [[ "${6:-}" == "nginx" ]]; then
    echo "nginx-container"
  fi
  exit 0
fi
if [[ "${1:-}" == "inspect" && "${2:-}" == "-f" && "${4:-}" == "nginx-container" ]]; then
  if [[ "${3:-}" == "{{.State.Status}}" ]]; then
    echo "restarting"
    exit 0
  fi
  if [[ "${3:-}" == "{{json .HostConfig.PortBindings}}" ]]; then
    echo '{"80/tcp":[{"HostIp":"127.0.0.1","HostPort":"18080"}],"443/tcp":[{"HostIp":"127.0.0.1","HostPort":"18443"}]}'
    exit 0
  fi
  exit 1
fi
if [[ "${1:-}" == "exec" ]]; then
  exit 1
fi
exit 0
BASH);
        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");
        $this->writeExecutable($fakeBin.'/ss', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
cat <<'EOF'
LISTEN 0 128 127.0.0.1:18080 0.0.0.0:*
LISTEN 0 128 127.0.0.1:18443 0.0.0.0:*
EOF
BASH);
        $this->writeExecutable($fakeBin.'/lsof', "#!/usr/bin/env bash\nset -euo pipefail\nexit 1\n");
        $this->writeExecutable($fakeBin.'/netstat', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'bootstrap', 'dev'],
            $tempRoot,
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_AUTO_ASSIGN_PORTS' => 'true',
            ],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $envContents = file_get_contents($tempRoot.'/.env');

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertIsString($envContents);
        $this->assertMatchesRegularExpression('/^APP_PORT=127\.0\.0\.1:18080$/m', $envContents);
        $this->assertMatchesRegularExpression('/^APP_SSL_PORT=127\.0\.0\.1:18443$/m', $envContents);
        $this->assertStringNotContainsString('Port conflict detected:', $combinedOutput);
        $this->assertStringNotContainsString('APP_PORT: 127.0.0.1:18080 ->', $combinedOutput);
        $this->assertStringNotContainsString('APP_SSL_PORT: 127.0.0.1:18443 ->', $combinedOutput);
    }

    public function test_install_full_fails_loudly_without_attempting_composer_install_when_vendor_is_missing_and_package_network_is_unreachable(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
PROMETHEUS_WEB_CONFIG_FILE={$tempRoot}/.blue-team-vm/rendered/prometheus.web-config.yml
GRAFANA_ADMIN_SECRET_FILE={$tempRoot}/.blue-team-vm/runtime/grafana-admin-secret
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
if [[ "\${1:-}" == "exec" && "\${3:-}" == "true" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "test" && "\${4:-}" == "-f" && "\${5:-}" == "/var/www/html/vendor/autoload.php" ]]; then
  exit 1
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "composer" && "\${4:-}" == "install" ]]; then
  exit 23
fi
exit 1
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

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerOutput = (string) @file_get_contents($dockerLog);

        $this->assertSame(1, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('local vendor/ is missing and package network access is unavailable', $combinedOutput);
        $this->assertStringNotContainsString('exec jobs-boards-laravel.test composer install --no-interaction --prefer-dist', $dockerOutput);
    }

    public function test_install_quick_restarts_the_app_container_without_combined_compose_interpolation(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', ObsTestFixtures::bootstrapObsGeneratedEnvScript($tempRoot.'/.blue-team-vm'));

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
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'INSTALL_ASSUME_YES' => 'true',
            ],
            "y\n",
            20,
        );
        $process->run();

        $dockerOutput = (string) @file_get_contents($dockerLog);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertStringNotContainsString('restart jobs-boards-laravel.test', $dockerOutput);
        $this->assertStringNotContainsString('compose -f', $dockerOutput);
        $this->assertStringNotContainsString('compose restart laravel.test', $dockerOutput);
    }

    public function test_install_setup_admin_fast_fails_when_the_app_container_is_not_running(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);
        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', ObsTestFixtures::bootstrapObsGeneratedEnvScript($tempRoot.'/.blue-team-vm'));

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
exit 0
BASH);
        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");
        $this->writeExecutable($fakeBin.'/python3', "#!/usr/bin/env bash\nset -euo pipefail\npython3 \"$@\"\n");
        $this->writeExecutable($fakeBin.'/openssl', "#!/usr/bin/env bash\nset -euo pipefail\nopenssl \"$@\"\n");

        $process = new Process(
            [$scriptPath, 'setupAdmin', 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerOutput = (string) @file_get_contents($dockerLog);

        $this->assertSame(1, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString("Container 'jobs-boards-laravel.test' is not running.", $combinedOutput);
        $this->assertStringNotContainsString('Container ready', $combinedOutput);
        $this->assertStringNotContainsString('php artisan --version', $dockerOutput);
    }

    public function test_install_quick_refuses_destructive_reset_without_explicit_non_interactive_confirmation(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $bootstrapEnvLog = $tempRoot.'/bootstrap-env.log';
        $bootstrapObsLog = $tempRoot.'/bootstrap-obs.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);
        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$bootstrapEnvLog}"
exit 0
BASH);
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$bootstrapObsLog}"
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${2:-}" == "jobs-boards-laravel.test" && "\${3:-}" == "true" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" ]]; then
  exit 0
fi
exit 0
BASH);
        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'quick', 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerOutput = (string) @file_get_contents($dockerLog);
        $bootstrapEnvOutput = is_file($bootstrapEnvLog) ? (string) file_get_contents($bootstrapEnvLog) : '';
        $bootstrapObsOutput = is_file($bootstrapObsLog) ? (string) file_get_contents($bootstrapObsLog) : '';

        $this->assertSame(1, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('Refusing interactive prompt in non-interactive mode', $combinedOutput);
        $this->assertStringNotContainsString('php artisan migrate:fresh --force', $dockerOutput);
        $this->assertSame('', $bootstrapEnvOutput, $combinedOutput);
        $this->assertSame('', $bootstrapObsOutput, $combinedOutput);
        $this->assertStringNotContainsString('exec jobs-boards-laravel.test true', $dockerOutput);
    }

    public function test_install_reset_demo_requires_admin_password_before_destructive_reset(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $bootstrapEnvLog = $tempRoot.'/bootstrap-env.log';
        $bootstrapObsLog = $tempRoot.'/bootstrap-obs.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);
        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$bootstrapEnvLog}"
exit 0
BASH);
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$bootstrapObsLog}"
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${2:-}" == "jobs-boards-laravel.test" && "\${3:-}" == "true" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" ]]; then
  exit 0
fi
exit 0
BASH);
        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'reset-demo', 'dev'],
            $tempRoot,
            [
                'INSTALL_ASSUME_YES' => 'true',
                'PATH' => $fakeBin.':'.getenv('PATH'),
            ],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerOutput = (string) @file_get_contents($dockerLog);
        $bootstrapEnvOutput = is_file($bootstrapEnvLog) ? (string) file_get_contents($bootstrapEnvLog) : '';
        $bootstrapObsOutput = is_file($bootstrapObsLog) ? (string) file_get_contents($bootstrapObsLog) : '';

        $this->assertSame(1, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('Admin password is required in non-interactive mode.', $combinedOutput);
        $this->assertStringNotContainsString('php artisan migrate:fresh --force', $dockerOutput);
        $this->assertSame('', $bootstrapEnvOutput, $combinedOutput);
        $this->assertSame('', $bootstrapObsOutput, $combinedOutput);
        $this->assertStringNotContainsString('exec jobs-boards-laravel.test true', $dockerOutput);
    }

    public function test_install_reset_demo_prefers_env_app_url_for_headless_install(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        if (! is_dir($fakeBin)) {
            mkdir($fakeBin, 0777, true);
        }

        file_put_contents($tempRoot.'/.env', <<<ENV
APP_URL=https://192.168.153.100
APP_PORT=80
APP_SSL_PORT=443
ENV);
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', ObsTestFixtures::bootstrapObsGeneratedEnvScript($tempRoot.'/.blue-team-vm'));

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${2:-}" == "jobs-boards-laravel.test" && "\${3:-}" == "true" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "test" && "\${4:-}" == "-f" && "\${5:-}" == "/var/www/html/vendor/autoload.php" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "--version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "down" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "migrate:fresh" && "\${6:-}" == "--force" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "install:headless" ]]; then
  exit 17
fi
if [[ "\${1:-}" == "exec" && "\${4:-}" == "php" && "\${5:-}" == "artisan" && "\${6:-}" == "install:headless" ]]; then
  exit 17
fi
exit 0
BASH);
        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'reset-demo', 'production'],
            $tempRoot,
            [
                'INSTALL_ASSUME_YES' => 'true',
                'INSTALL_ADMIN_EMAIL' => 'admin@lab.local',
                'INSTALL_ADMIN_PASSWORD' => 'ChangeMe123!ChangeMe123!',
                'PATH' => $fakeBin.':'.getenv('PATH'),
            ],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerOutput = (string) @file_get_contents($dockerLog);

        $this->assertSame(17, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('--app-url=https://192.168.153.100', $dockerOutput);
        $this->assertStringNotContainsString('--app-url=https://localhost', $dockerOutput);
    }

    public function test_install_full_uses_non_destructive_migrate_and_npm_ci_without_container_restart(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', ObsTestFixtures::bootstrapObsGeneratedEnvScript($tempRoot.'/.blue-team-vm'));

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${2:-}" == "jobs-boards-laravel.test" && "\${3:-}" == "true" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "test" && "\${4:-}" == "-f" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "--version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "optimize:clear" ]]; then
  exit 17
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "npm" && "\${4:-}" == "ci" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "npm" && "\${4:-}" == "run" && "\${5:-}" == "build" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "migrate" && "\${6:-}" == "--force" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "db:seed" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "restart" ]]; then
  exit 23
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

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerOutput = (string) @file_get_contents($dockerLog);
        $migrateOffset = strpos($dockerOutput, 'exec jobs-boards-laravel.test php artisan migrate --force');
        $npmCiOffset = strpos($dockerOutput, 'exec jobs-boards-laravel.test npm ci');
        $optimizeOffset = strpos($dockerOutput, 'exec jobs-boards-laravel.test php artisan optimize:clear');

        $this->assertSame(17, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('exec jobs-boards-laravel.test npm ci', $dockerOutput);
        $this->assertStringContainsString('exec jobs-boards-laravel.test php artisan migrate --force', $dockerOutput);
        $this->assertNotFalse($migrateOffset);
        $this->assertNotFalse($npmCiOffset);
        $this->assertNotFalse($optimizeOffset);
        $this->assertLessThan($npmCiOffset, $migrateOffset);
        $this->assertLessThan($optimizeOffset, $npmCiOffset);
        $this->assertStringNotContainsString('exec jobs-boards-laravel.test npm install', $dockerOutput);
        $this->assertStringNotContainsString('exec jobs-boards-laravel.test php artisan migrate:fresh --force', $dockerOutput);
        $this->assertStringNotContainsString('restart jobs-boards-laravel.test', $dockerOutput);
    }

    public function test_install_full_runs_migrations_before_optimize_clear_when_database_cache_tables_are_missing(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', ObsTestFixtures::bootstrapObsGeneratedEnvScript($tempRoot.'/.blue-team-vm'));

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
state_file="{$tempRoot}/migrated"
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${2:-}" == "jobs-boards-laravel.test" && "\${3:-}" == "true" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "test" && "\${4:-}" == "-f" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "--version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "down" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "migrate" && "\${6:-}" == "--force" ]]; then
  : > "\${state_file}"
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "db:seed" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "npm" && "\${4:-}" == "ci" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "npm" && "\${4:-}" == "run" && "\${5:-}" == "build" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "optimize:clear" ]]; then
  if [[ -f "\${state_file}" ]]; then
    exit 17
  fi
  exit 23
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "php" && "\${4:-}" == "artisan" && "\${5:-}" == "up" ]]; then
  exit 0
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

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerOutput = (string) @file_get_contents($dockerLog);
        $migrateOffset = strpos($dockerOutput, 'exec jobs-boards-laravel.test php artisan migrate --force');
        $optimizeOffset = strpos($dockerOutput, 'exec jobs-boards-laravel.test php artisan optimize:clear');

        $this->assertSame(17, $process->getExitCode(), $combinedOutput);
        $this->assertNotFalse($migrateOffset, 'Expected deploy flow to run migrations.');
        $this->assertNotFalse($optimizeOffset, 'Expected deploy flow to clear optimized caches after schema setup.');
        $this->assertLessThan($optimizeOffset, $migrateOffset, 'Migrations must happen before optimize:clear when database-backed cache tables do not exist yet.');
    }

    public function test_install_full_does_not_treat_host_vendor_as_runtime_truth_when_container_vendor_is_missing(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/bootstrap', 0777, true);
        mkdir($tempRoot.'/vendor', 0777, true);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        file_put_contents($tempRoot.'/vendor/autoload.php', "<?php\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', ObsTestFixtures::bootstrapObsGeneratedEnvScript($tempRoot.'/.blue-team-vm'));

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${2:-}" == "jobs-boards-laravel.test" && "\${3:-}" == "true" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "test" && "\${4:-}" == "-f" && "\${5:-}" == "/var/www/html/vendor/autoload.php" ]]; then
  exit 1
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "curl" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${3:-}" == "composer" && "\${4:-}" == "install" ]]; then
  exit 17
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

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerOutput = (string) @file_get_contents($dockerLog);

        $this->assertSame(17, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('exec jobs-boards-laravel.test composer install --no-interaction --prefer-dist', $dockerOutput);
    }

    public function test_install_script_drops_external_qr_fallback_and_tmp_secret_spoolers_by_default(): void
    {
        $contents = file_get_contents($this->repoRoot.'/install.sh');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('api.qrserver.com', $contents);
        $this->assertStringNotContainsString('/tmp/admin-', $contents);
        $this->assertStringNotContainsString('/tmp/monitoring-', $contents);
        $this->assertStringNotContainsString('INSTALL_ADMIN_PASSWORD=', $contents);
        $this->assertStringNotContainsString('INSTALL_HEADLESS_ADMIN_PASSWORD=', $contents);
        $this->assertStringContainsString('--password-file=php://stdin', $contents);
        $this->assertStringContainsString('--admin-password-file=php://stdin', $contents);
    }

    public function test_install_test_prepare_rejects_invalid_testing_database_identifier_before_invoking_psql(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\nDB_USERNAME=jobs_user\n");
        file_put_contents($tempRoot.'/.env.testing', "DB_DATABASE=testing;DROP DATABASE jobs_boards;--\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${2:-}" == "jobs-boards-laravel.test" && "\${3:-}" == "true" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "compose" && "\${2:-}" == "-f" && "\${4:-}" == "ps" && "\${5:-}" == "-q" && "\${6:-}" == "postgres" ]]; then
  printf 'jobs-boards-postgres\n'
  exit 0
fi
if [[ "\${1:-}" == "exec" ]]; then
  exit 0
fi
exit 0
BASH);

        $process = new Process(
            [$scriptPath, 'test', 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerOutput = (string) @file_get_contents($dockerLog);

        $this->assertSame(1, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('Invalid PostgreSQL identifier for DB_DATABASE', $combinedOutput);
        $this->assertStringNotContainsString('exec jobs-boards-postgres psql', $dockerOutput);
    }

    public function test_install_test_prepare_uses_sanitized_env_values_when_creating_the_testing_database(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $dockerLog = $tempRoot.'/docker.log';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\nDB_USERNAME=\"jobs_user\"\n");
        file_put_contents($tempRoot.'/.env.testing', "DB_DATABASE='testing_db'\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $this->writeExecutable($fakeBin.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${2:-}" == "jobs-boards-laravel.test" && "\${3:-}" == "true" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "compose" && "\${2:-}" == "-f" && "\${4:-}" == "ps" && "\${5:-}" == "-q" && "\${6:-}" == "postgres" ]]; then
  printf 'jobs-boards-postgres\n'
  exit 0
fi
if [[ "\${1:-}" == "exec" && "\${2:-}" == "jobs-boards-postgres" && "\${3:-}" == "psql" ]]; then
  exit 17
fi
if [[ "\${1:-}" == "exec" ]]; then
  exit 0
fi
exit 0
BASH);

        $process = new Process(
            [$scriptPath, 'test', 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerOutput = (string) @file_get_contents($dockerLog);

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('compose -f '.$tempRoot.'/compose.yaml ps -q postgres', $dockerOutput);
        $this->assertStringContainsString('exec jobs-boards-postgres psql -U jobs_user -d postgres -c CREATE DATABASE testing_db;', $dockerOutput);
        $this->assertStringNotContainsString('Invalid PostgreSQL identifier', $combinedOutput);
    }

    public function test_install_skip_reports_the_effective_compose_file_in_summary(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installScriptFixture($tempRoot);
        $fakeBin = $tempRoot.'/fake-bin';
        $customComposeFile = $tempRoot.'/compose.custom.yml';

        mkdir($fakeBin, 0777, true);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        file_put_contents($customComposeFile, "services: {}\n");

        $this->writeExecutable($fakeBin.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "compose" && "${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "${1:-}" == "exec" ]]; then
  exit 0
fi
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/jq', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'skip', 'dev'],
            $tempRoot,
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'INSTALL_COMPOSE_FILE' => $customComposeFile,
            ],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('docker compose -f ./compose.custom.yml ps', $combinedOutput);
        $this->assertStringContainsString('docker compose -f ./compose.custom.yml logs', $combinedOutput);
        $this->assertStringNotContainsString('docker compose -f compose.yaml ps', $combinedOutput);
    }

    public function test_combined_compose_requires_generated_obs_runtime_artifacts(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.yaml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('${MONITORING_ADMIN_USERNAME:?Set MONITORING_ADMIN_USERNAME before docker compose up}', $contents);
        $this->assertStringContainsString('${MONITORING_PASSWORD_HASH:?Set MONITORING_PASSWORD_HASH before docker compose up}', $contents);
        $this->assertStringContainsString('${SESSION_SECRET:?Set SESSION_SECRET before docker compose up}', $contents);
        $this->assertStringContainsString('${PROMETHEUS_PASSWORD_HASH:?Set PROMETHEUS_PASSWORD_HASH before docker compose up}', $contents);
        $this->assertStringContainsString(ObsConfigContract::fallbackExpression('PROMETHEUS_WEB_CONFIG_FILE'), $contents);
        $this->assertStringContainsString(ObsConfigContract::fallbackExpression('GRAFANA_DATASOURCES_FILE'), $contents);
        $this->assertStringContainsString(ObsConfigContract::fallbackExpression('GRAFANA_ADMIN_SECRET_FILE'), $contents);
        $this->assertStringContainsString('GF_SECURITY_ADMIN_PASSWORD__FILE: /run/secrets/grafana_admin_secret', $contents);
        $this->assertStringContainsString('GRAFANA_POSTGRES_URL: ${GRAFANA_POSTGRES_URL:-postgres:5432}', $contents);
        $this->assertStringContainsString('GRAFANA_POSTGRES_DATABASE: ${DB_DATABASE}', $contents);
        $this->assertStringContainsString('GRAFANA_POSTGRES_USER: ${DB_USERNAME}', $contents);
        $this->assertStringContainsString('GRAFANA_POSTGRES_SECRET: ${DB_PASSWORD}', $contents);
        $this->assertStringContainsString('GRAFANA_POSTGRES_SSLMODE: ${GRAFANA_POSTGRES_SSLMODE:-prefer}', $contents);
        $this->assertStringContainsString(ObsConfigContract::fallbackExpression('GRAFANA_DATASOURCES_FILE').':/etc/grafana/provisioning/datasources/datasources.yaml:ro', $contents);
        $this->assertStringNotContainsString('${PROMETHEUS_WEB_CONFIG_FILE:-./docker/prometheus/web-config.yml}', $contents);
        $this->assertStringNotContainsString('DB_DATABASE: "${DB_DATABASE}"', $contents);
        $this->assertStringNotContainsString('DB_USERNAME: "${DB_USERNAME}"', $contents);
        $this->assertStringNotContainsString('DB_PASSWORD: "${DB_PASSWORD}"', $contents);
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
        $this->assertStringContainsString('${BT_HONEYPOT_SOURCE:-./docker/nginx/includes/blue-team-honeypot.conf}:/etc/nginx/includes/blue-team-honeypot.conf:ro', $contents);
        $this->assertStringContainsString('curl -kfsS https://127.0.0.1/up || exit 1', $contents);
        $this->assertStringNotContainsString('curl -sf http://laravel.test:80/up || exit 1', $contents);
    }

    public function test_combined_compose_passes_monitoring_access_policy_env_to_nginx(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.yaml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('MONITORING_ACCESS_MODE: "${MONITORING_ACCESS_MODE:-internal-only}"', $contents);
        $this->assertStringContainsString('MONITORING_ALLOWED_CIDRS: "${MONITORING_ALLOWED_CIDRS:-127.0.0.1/32,172.30.0.0/24}"', $contents);
        $this->assertStringNotContainsString('MONITORING_PASSWORD: "${MONITORING_PASSWORD}"', $contents);
    }

    public function test_combined_compose_mounts_the_laravel_public_tree_into_nginx_for_static_assets(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.yaml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('- ".:/var/www/html:ro"', $contents);
    }

    public function test_install_script_relies_on_shared_compose_honeypot_preload_instead_of_inline_export(): void
    {
        $contents = file_get_contents($this->repoRoot.'/install.sh');
        $commonContents = file_get_contents($this->repoRoot.'/ops/lib/common.sh');

        $this->assertIsString($contents);
        $this->assertIsString($commonContents);
        $this->assertStringNotContainsString('export BT_HONEYPOT_SOURCE="${BT_HONEYPOT_SOURCE:-${ROOT_DIR}/docker/nginx/includes/blue-team-honeypot.conf}"', $contents);
        $this->assertStringNotContainsString('BT_HONEYPOT_SOURCE="${BT_HONEYPOT_SOURCE}"', $contents);
        $this->assertStringContainsString('bt_compose "$INSTALL_COMPOSE_FILE" "$@"', $contents);
        $this->assertStringContainsString('bt_resolve_honeypot_source()', $commonContents);
        $this->assertStringNotContainsString(': "${BT_HONEYPOT_SOURCE:=/opt/blue-team/nginx/includes/blue-team-honeypot.conf}"', $commonContents);
    }

    public function test_combined_compose_mirrors_app_plane_crowdsec_serving_path_contract(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.yaml');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('entrypoint: ["/entrypoint.sh"]', $contents);
        $this->assertStringNotContainsString('./docker/crowdsec/entrypoint.sh:/entrypoint.sh:ro', $contents);
        $this->assertStringContainsString('./docker/crowdsec/appsec-configs/appsec-default.yaml:/etc/crowdsec/appsec-configs/appsec-default.yaml:ro', $contents);
        $this->assertStringContainsString('./docker/crowdsec/acquis.d/fp-trap.yaml:/etc/crowdsec/acquis.d/fp-trap.yaml:ro', $contents);
        $this->assertStringContainsString('CROWDSEC_REQUIRED_APPSEC_CONFIG: "${CROWDSEC_REQUIRED_APPSEC_CONFIG:-crowdsecurity/appsec-default}"', $contents);
        $this->assertStringContainsString('CROWDSEC_REQUIRED_APPSEC_COLLECTIONS: "${CROWDSEC_REQUIRED_APPSEC_COLLECTIONS:-crowdsecurity/appsec-virtual-patching}"', $contents);
        $this->assertStringContainsString('COLLECTIONS: "${CROWDSEC_REQUIRED_APPSEC_COLLECTIONS:-crowdsecurity/appsec-virtual-patching}"', $contents);
        $this->assertStringContainsString('APPSEC_CONFIGS: "${CROWDSEC_REQUIRED_APPSEC_CONFIG:-crowdsecurity/appsec-default}"', $contents);
        $this->assertStringContainsString('ENROLL_KEY: "${CROWDSEC_ENROLL_KEY:-}"', $contents);
        $this->assertStringContainsString('DISABLE_ONLINE_API: "${CROWDSEC_DISABLE_ONLINE_API:-true}"', $contents);
        $this->assertStringContainsString('cscli appsec-configs list -c /etc/crowdsec/config.yaml', $contents);
        $this->assertStringContainsString('grep -Fq "${CROWDSEC_REQUIRED_APPSEC_CONFIG:-crowdsecurity/appsec-default}"', $contents);
        $this->assertStringContainsString("cscli appsec-rules list -c /etc/crowdsec/config.yaml", $contents);
        $this->assertStringContainsString("grep -Fq ''crowdsecurity/vpatch-''", $contents);
        $this->assertStringContainsString('wget -qO- http://127.0.0.1:8080/health >/dev/null 2>&1 || exit 1', $contents);
        $this->assertStringNotContainsString('cscli version || exit 1', $contents);
    }

    public function test_combined_compose_waits_for_crowdsec_key_initialization_before_starting_nginx(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.yaml');

        $this->assertIsString($contents);
        $this->assertMatchesRegularExpression(
            "/^    nginx:\\n(?:(?:        |            ).*\\n)*?        depends_on:\\n            laravel\\.test:\\n                condition: service_started\\n            crowdsec-key-init:\\n                condition: service_completed_successfully\\n/m",
            $contents
        );
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-install-shell-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
    }

    private function installScriptFixture(string $tempRoot): string
    {
        $scriptPath = $tempRoot.'/install.sh';
        $commonLibPath = $tempRoot.'/ops/lib/common.sh';
        $nginxSslBootstrapPath = $tempRoot.'/ops/bootstrap/bootstrap-nginx-ssl.sh';

        mkdir(dirname($commonLibPath), 0777, true);
        copy($this->repoRoot.'/install.sh', $scriptPath);
        ObsTestFixtures::installCommonLibFixture($this->repoRoot, $tempRoot);
        $this->writeExecutable($nginxSslBootstrapPath, "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");
        chmod($scriptPath, 0755);
        chmod($commonLibPath, 0755);

        return $scriptPath;
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
