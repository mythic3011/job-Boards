<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\ObsConfigContract;
use Tests\Support\ObsTestFixtures;

/**
 * Verification path: sqlite-safe.
 */
class BootstrapEnvShellContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_bootstrap_env_uses_portable_python_handoff_and_keeps_atomic_write(): void
    {
        $contents = file_get_contents($this->repoRoot.'/bootstrap-env.sh');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('@Q', $contents);
        $this->assertStringContainsString('BOOTSTRAP_ENV_SET_KEY', $contents);
        $this->assertStringContainsString('BOOTSTRAP_ENV_SET_VALUE', $contents);
        $this->assertStringContainsString('os.replace', $contents);
    }

    public function test_bootstrap_env_audits_the_canonical_audit_auth_service_secret_contract_via_generic_secret_classification(): void
    {
        $contents = file_get_contents($this->repoRoot.'/bootstrap-env.sh');
        $example = file_get_contents($this->repoRoot.'/.env.advanced.example');

        $this->assertIsString($contents);
        $this->assertIsString($example);
        $this->assertStringContainsString('SECRET|KEY|TOKEN|PASSWORD', $contents);
        $this->assertStringNotContainsString('CANONICAL_AUDIT_AUTH_SERVICE_SECRET=', $example);
    }

    public function test_bootstrap_env_keeps_monitoring_password_as_the_only_primary_plaintext_operator_input(): void
    {
        $contents = file_get_contents($this->repoRoot.'/bootstrap-env.sh');
        $example = file_get_contents($this->repoRoot.'/.env.advanced.example');

        $this->assertIsString($contents);
        $this->assertIsString($example);
        $this->assertStringNotContainsString('for var in MONITORING_PASSWORD GRAFANA_PASSWORD PROMETHEUS_PASSWORD; do', $contents);
        $this->assertStringNotContainsString('docker/nginx/htpasswd/monitoring.htpasswd', $contents);
        $this->assertStringContainsString('MONITORING_PASSWORD=', $example);
        $this->assertStringNotContainsString('GRAFANA_PASSWORD=', $example);
        $this->assertStringNotContainsString('PROMETHEUS_PASSWORD=', $example);
    }

    public function test_bootstrap_env_uses_shared_config_authority_for_grafana_secret_path_derivation(): void
    {
        $contents = file_get_contents($this->repoRoot.'/bootstrap-env.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('bt_config_resolve_key GRAFANA_ADMIN_SECRET_FILE', $contents);
        $this->assertStringNotContainsString(ObsConfigContract::derivedPath(ObsConfigContract::DEFAULT_STATE_DIR, 'GRAFANA_ADMIN_SECRET_FILE'), $contents);
    }

    public function test_bootstrap_env_persists_detected_or_default_shared_app_plane_network_into_env(): void
    {
        $contents = file_get_contents($this->repoRoot.'/bootstrap-env.sh');
        $example = file_get_contents($this->repoRoot.'/.env.advanced.example');

        $this->assertIsString($contents);
        $this->assertIsString($example);
        $this->assertStringContainsString('sync_app_plane_network_contract()', $contents);
        $this->assertStringContainsString('bt_preload_compose_app_plane_network', $contents);
        $this->assertStringContainsString('bt_default_app_plane_network_name', $contents);
        $this->assertStringContainsString('set_env "BT_APP_PLANE_NETWORK_NAME"', $contents);
        $this->assertStringNotContainsString('BT_APP_PLANE_NETWORK_NAME=', $example);
    }

    public function test_shell_port_reassignment_uses_named_allocator_calls_instead_of_command_substitution(): void
    {
        $bootstrapContents = file_get_contents($this->repoRoot.'/bootstrap-env.sh');
        $installContents = file_get_contents($this->repoRoot.'/install.sh');

        $this->assertIsString($bootstrapContents);
        $this->assertIsString($installContents);
        $this->assertStringNotContainsString('new_port=$(bt_find_free_port)', $bootstrapContents);
        $this->assertStringNotContainsString('new_port=$(bt_find_free_port)', $installContents);
    }

    public function test_set_env_preserves_special_character_semantics_with_portable_handoff(): void
    {
        $tempRoot = $this->makeTempDir();
        file_put_contents($tempRoot.'/.env', "EXISTING=old value\nKEEP=stable\n");

        $helperPath = $tempRoot.'/exercise-set-env.sh';
        $setEnvFunction = $this->extractSetEnvFunction();

        $script = <<<BASH
#!/usr/bin/env bash
set -euo pipefail
{$setEnvFunction}

cd "\$1"
set_env "EXISTING" "\${VALUE_EXISTING}"
set_env "SPACES" "\${VALUE_SPACES}"
set_env "QUOTES" "\${VALUE_QUOTES}"
set_env "DOLLARS" "\${VALUE_DOLLARS}"
set_env "HASH_EQUALS" "\${VALUE_HASH_EQUALS}"
set_env "EMPTY" ""
BASH;

        $this->writeExecutable($helperPath, $script);

        $process = new Process(
            [$helperPath, $tempRoot],
            $tempRoot,
            [
                'VALUE_EXISTING' => ' updated value with spaces ',
                'VALUE_SPACES' => '  leading and trailing  ',
                'VALUE_QUOTES' => "\"double\" and 'single'",
                'VALUE_DOLLARS' => '$alpha$$beta # tail',
                'VALUE_HASH_EQUALS' => 'value#with=delimiters',
            ],
            null,
            20,
        );
        $process->mustRun();

        $contents = file_get_contents($tempRoot.'/.env');

        $this->assertIsString($contents);
        $this->assertSame(
            "EXISTING= updated value with spaces \n".
            "KEEP=stable\n".
            "SPACES=  leading and trailing  \n".
            "QUOTES=\"double\" and 'single'\n".
            "DOLLARS=\$alpha\$\$beta # tail\n".
            "HASH_EQUALS=value#with=delimiters\n".
            "EMPTY=\n",
            $contents,
        );
    }

    public function test_set_env_updates_symlink_target_without_replacing_the_env_symlink(): void
    {
        $tempRoot = $this->makeTempDir();
        file_put_contents($tempRoot.'/shared.env', "DB_PASSWORD=old-secret\n");
        symlink($tempRoot.'/shared.env', $tempRoot.'/.env');

        $helperPath = $tempRoot.'/exercise-set-env-symlink.sh';
        $setEnvFunction = $this->extractSetEnvFunction();

        $script = <<<BASH
#!/usr/bin/env bash
set -euo pipefail
{$setEnvFunction}

cd "\$1"
set_env "DB_PASSWORD" "\${VALUE_DB_PASSWORD}"
BASH;

        $this->writeExecutable($helperPath, $script);

        $process = new Process(
            [$helperPath, $tempRoot],
            $tempRoot,
            [
                'VALUE_DB_PASSWORD' => 'new-secret',
            ],
            null,
            20,
        );
        $process->mustRun();

        $this->assertTrue(is_link($tempRoot.'/.env'));
        $this->assertSame($tempRoot.'/shared.env', readlink($tempRoot.'/.env'));
        $this->assertSame("DB_PASSWORD=new-secret\n", file_get_contents($tempRoot.'/shared.env'));
    }

    public function test_empty_local_only_aws_values_are_not_treated_as_weak_secrets(): void
    {
        $tempRoot = $this->makeTempDir();
        $helperPath = $tempRoot.'/exercise-is-weak.sh';
        $isLocalOnlyAwsVarFunction = $this->extractFunction('is_local_only_aws_var');
        $isSecretVarFunction = $this->extractFunction('is_secret_var');
        $isDerivedVarFunction = $this->extractFunction('is_derived_var');
        $isIdentifierVarFunction = $this->extractFunction('is_identifier_var');
        $isWeakFunction = $this->extractFunction('is_weak');

        $script = <<<BASH
#!/usr/bin/env bash
set -euo pipefail
{$isLocalOnlyAwsVarFunction}
{$isSecretVarFunction}
{$isDerivedVarFunction}
{$isIdentifierVarFunction}
{$isWeakFunction}

if is_weak "" "AWS_SECRET_ACCESS_KEY"; then
    printf 'weak\n'
else
    printf 'ok\n'
fi
BASH;

        $this->writeExecutable($helperPath, $script);

        $process = new Process([$helperPath], $tempRoot, null, null, 20);
        $process->mustRun();

        $this->assertSame("ok\n", $process->getOutput());
    }

    public function test_final_audit_dispatches_local_only_aws_vars_to_the_inactive_local_stack_handler(): void
    {
        $contents = file_get_contents($this->repoRoot.'/bootstrap-env.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('audit_inactive_local_stack_secret()', $contents);
        $this->assertStringContainsString('elif is_local_only_aws_var "$var"; then', $contents);
        $this->assertStringContainsString('audit_inactive_local_stack_secret "$var"', $contents);

        $localOnlyOffset = strpos($contents, 'elif is_local_only_aws_var "$var"; then');
        $secretOffset = strpos($contents, 'elif is_secret_var "$var"; then');

        $this->assertNotFalse($localOnlyOffset);
        $this->assertNotFalse($secretOffset);
        $this->assertLessThan($secretOffset, $localOnlyOffset);
    }

    public function test_bootstrap_env_keeps_ports_already_owned_by_the_running_stack(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->bootstrapEnvFixture($tempRoot);
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=80
APP_SSL_PORT=443
VITE_PORT=5173
FORWARD_DB_PORT=5432
FORWARD_REDIS_PORT=6379
MONITORING_ADMIN_USERNAME=admin
MONITORING_PASSWORD=already-set-monitoring-password
ENV);

        $this->writeExecutable($fakeBin.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
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
exit 0
BASH);
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
            [$scriptPath, 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->mustRun();

        $output = $process->getOutput().$process->getErrorOutput();
        $envContents = file_get_contents($tempRoot.'/.env');

        $this->assertIsString($envContents);
        $this->assertMatchesRegularExpression('/^APP_PORT=80$/m', $envContents);
        $this->assertMatchesRegularExpression('/^APP_SSL_PORT=443$/m', $envContents);
        $this->assertStringContainsString('✔ APP_PORT=80', $output);
        $this->assertStringContainsString('✔ APP_SSL_PORT=443', $output);
        $this->assertStringNotContainsString('APP_PORT: 80 →', $output);
        $this->assertStringNotContainsString('APP_SSL_PORT: 443 →', $output);
    }

    public function test_bootstrap_env_keeps_host_bound_ports_already_owned_by_the_running_stack(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->bootstrapEnvFixture($tempRoot);
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=127.0.0.1:18080
APP_SSL_PORT=127.0.0.1:18443
VITE_PORT=5173
FORWARD_DB_PORT=5432
FORWARD_REDIS_PORT=6379
MONITORING_ADMIN_USERNAME=admin
MONITORING_PASSWORD=already-set-monitoring-password
ENV);

        $this->writeExecutable($fakeBin.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
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
    echo '{"80/tcp":[{"HostIp":"127.0.0.1","HostPort":"18080"}],"443/tcp":[{"HostIp":"127.0.0.1","HostPort":"18443"}]}'
    exit 0
  fi
  exit 1
fi
if [[ "${1:-}" == "port" && "${2:-}" == "nginx-container" ]]; then
  exit 0
fi
exit 0
BASH);
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
            [$scriptPath, 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->mustRun();

        $output = $process->getOutput().$process->getErrorOutput();
        $envContents = file_get_contents($tempRoot.'/.env');

        $this->assertIsString($envContents);
        $this->assertMatchesRegularExpression('/^APP_PORT=127\.0\.0\.1:18080$/m', $envContents);
        $this->assertMatchesRegularExpression('/^APP_SSL_PORT=127\.0\.0\.1:18443$/m', $envContents);
        $this->assertStringContainsString('✔ APP_PORT=127.0.0.1:18080', $output);
        $this->assertStringContainsString('✔ APP_SSL_PORT=127.0.0.1:18443', $output);
        $this->assertStringNotContainsString('APP_PORT: 127.0.0.1:18080 →', $output);
        $this->assertStringNotContainsString('APP_SSL_PORT: 127.0.0.1:18443 →', $output);
    }

    public function test_bootstrap_env_keeps_host_bound_ports_when_current_stack_nginx_is_restarting(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->bootstrapEnvFixture($tempRoot);
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=127.0.0.1:18080
APP_SSL_PORT=127.0.0.1:18443
VITE_PORT=5173
FORWARD_DB_PORT=5432
FORWARD_REDIS_PORT=6379
MONITORING_ADMIN_USERNAME=admin
MONITORING_PASSWORD=already-set-monitoring-password
ENV);

        $this->writeExecutable($fakeBin.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
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
exit 0
BASH);
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
            [$scriptPath, 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->mustRun();

        $output = $process->getOutput().$process->getErrorOutput();
        $envContents = file_get_contents($tempRoot.'/.env');

        $this->assertIsString($envContents);
        $this->assertMatchesRegularExpression('/^APP_PORT=127\.0\.0\.1:18080$/m', $envContents);
        $this->assertMatchesRegularExpression('/^APP_SSL_PORT=127\.0\.0\.1:18443$/m', $envContents);
        $this->assertStringContainsString('✔ APP_PORT=127.0.0.1:18080', $output);
        $this->assertStringContainsString('✔ APP_SSL_PORT=127.0.0.1:18443', $output);
        $this->assertStringNotContainsString('APP_PORT: 127.0.0.1:18080 →', $output);
        $this->assertStringNotContainsString('APP_SSL_PORT: 127.0.0.1:18443 →', $output);
    }

    public function test_bootstrap_env_fails_closed_for_true_port_conflicts_in_non_interactive_mode(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->bootstrapEnvFixture($tempRoot);
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        file_put_contents($tempRoot.'/.env', <<<ENV
APP_PORT=80
APP_SSL_PORT=443
VITE_PORT=5173
FORWARD_DB_PORT=5432
FORWARD_REDIS_PORT=6379
MONITORING_ADMIN_USERNAME=admin
MONITORING_PASSWORD=already-set-monitoring-password
ENV);

        $this->writeExecutable($fakeBin.'/docker', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");
        $this->writeExecutable($fakeBin.'/ss', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
cat <<'EOF'
LISTEN 0 128 0.0.0.0:80 0.0.0.0:*
EOF
BASH);
        $this->writeExecutable($fakeBin.'/lsof', "#!/usr/bin/env bash\nset -euo pipefail\nexit 1\n");
        $this->writeExecutable($fakeBin.'/netstat', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $process = new Process(
            [$scriptPath, 'dev'],
            $tempRoot,
            ['PATH' => $fakeBin.':'.getenv('PATH')],
            null,
            20,
        );
        $process->run();

        $output = $process->getOutput().$process->getErrorOutput();
        $envContents = file_get_contents($tempRoot.'/.env');

        $this->assertSame(1, $process->getExitCode(), $output);
        $this->assertIsString($envContents);
        $this->assertMatchesRegularExpression('/^APP_PORT=80$/m', $envContents);
        $this->assertStringContainsString('Port conflicts detected in non-interactive mode', $output);
        $this->assertStringContainsString('⚠ 1 issue(s) remaining — review manually', $output);
        $this->assertStringNotContainsString('APP_PORT: 80 →', $output);
    }

    private function extractSetEnvFunction(): string
    {
        return $this->extractFunction('set_env');
    }

    private function extractFunction(string $name): string
    {
        $contents = file_get_contents($this->repoRoot.'/bootstrap-env.sh');
        $this->assertIsString($contents);

        $pattern = sprintf('/^%s\\(\\) \\{\n.*?^\\}/ms', preg_quote($name, '/'));
        if (! preg_match($pattern, $contents, $matches)) {
            $this->fail(sprintf('Could not extract %s() from bootstrap-env.sh', $name));
        }

        return $matches[0];
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-bootstrap-env-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
    }

    private function bootstrapEnvFixture(string $tempRoot): string
    {
        $scriptPath = $tempRoot.'/bootstrap-env.sh';

        copy($this->repoRoot.'/bootstrap-env.sh', $scriptPath);
        copy($this->repoRoot.'/.env.example', $tempRoot.'/.env.example');
        ObsTestFixtures::installCommonLibFixture($this->repoRoot, $tempRoot);
        chmod($scriptPath, 0755);

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
