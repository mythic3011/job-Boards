<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\ObsConfigContract;

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
        $example = file_get_contents($this->repoRoot.'/.env.example');

        $this->assertIsString($contents);
        $this->assertIsString($example);
        $this->assertStringContainsString('SECRET|KEY|TOKEN|PASSWORD', $contents);
        $this->assertStringContainsString('CANONICAL_AUDIT_AUTH_SERVICE_SECRET=', $example);
    }

    public function test_bootstrap_env_keeps_monitoring_password_as_the_only_primary_plaintext_operator_input(): void
    {
        $contents = file_get_contents($this->repoRoot.'/bootstrap-env.sh');
        $example = file_get_contents($this->repoRoot.'/.env.example');

        $this->assertIsString($contents);
        $this->assertIsString($example);
        $this->assertStringNotContainsString('for var in MONITORING_PASSWORD GRAFANA_PASSWORD PROMETHEUS_PASSWORD; do', $contents);
        $this->assertStringNotContainsString('docker/nginx/htpasswd/monitoring.htpasswd', $contents);
        $this->assertStringContainsString('# advanced override: plaintext source for Grafana admin bootstrap; defaults to MONITORING_PASSWORD when unset', $example);
        $this->assertStringContainsString('# advanced override: plaintext source for Prometheus basic auth bootstrap; defaults to MONITORING_PASSWORD when unset', $example);
    }

    public function test_bootstrap_env_uses_shared_config_authority_for_grafana_secret_path_derivation(): void
    {
        $contents = file_get_contents($this->repoRoot.'/bootstrap-env.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('bt_config_resolve_key GRAFANA_ADMIN_SECRET_FILE', $contents);
        $this->assertStringNotContainsString(ObsConfigContract::derivedPath(ObsConfigContract::DEFAULT_STATE_DIR, 'GRAFANA_ADMIN_SECRET_FILE'), $contents);
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

    private function extractSetEnvFunction(): string
    {
        $contents = file_get_contents($this->repoRoot.'/bootstrap-env.sh');
        $this->assertIsString($contents);

        if (! preg_match('/^set_env\\(\\) \\{\n.*?^\\}/ms', $contents, $matches)) {
            $this->fail('Could not extract set_env() from bootstrap-env.sh');
        }

        return $matches[0];
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-bootstrap-env-'.bin2hex(random_bytes(8));
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
