<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class SetupShellContractTest extends TestCase
{
    public function test_setup_shell_wraps_install_with_demo_ready_defaults(): void
    {
        $repoRoot = dirname(__DIR__, 2);
        $contents = file_get_contents($repoRoot.'/setup.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('#!/usr/bin/env bash', $contents);
        $this->assertStringContainsString('MODE="${1:-${SETUP_MODE:-reset-demo}}"', $contents);
        $this->assertStringContainsString('ENV_MODE="${2:-${SETUP_ENV_MODE:-dev}}"', $contents);
        $this->assertStringContainsString('SSL_SWITCH_TARGET_MODE="${2:-${SETUP_SSL_SWITCH_TARGET:-}}"', $contents);
        $this->assertStringContainsString('demo|full|quick|setupAdmin|skip|test', $contents);
        $this->assertStringContainsString('ssl-switch', $contents);
        $this->assertStringContainsString('export INSTALL_SAVE_CREDS="${INSTALL_SAVE_CREDS:-true}"', $contents);
        $this->assertStringContainsString('exec "${ROOT_DIR}/install.sh" "${MODE}" "${ENV_MODE}"', $contents);
    }

    public function test_setup_shell_forwards_ssl_switch_target_before_env_mode(): void
    {
        $tempRoot = $this->makeTempDir();
        $setupScript = $tempRoot.'/setup.sh';
        $capturedArgs = $tempRoot.'/install.args';
        $capturedEnv = $tempRoot.'/install.env';

        copy(dirname(__DIR__, 2).'/setup.sh', $setupScript);
        chmod($setupScript, 0755);

        $this->writeExecutable($tempRoot.'/install.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$@" > "{$capturedArgs}"
cat > "{$capturedEnv}" <<EOF
INSTALL_SAVE_CREDS=\${INSTALL_SAVE_CREDS:-}
INSTALL_SSL_SWITCH_TARGET=\${INSTALL_SSL_SWITCH_TARGET:-}
EOF
BASH);

        $process = new Process([$setupScript, 'ssl-switch', 'letsencrypt', 'production'], $tempRoot);
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertSame(
            "ssl-switch\nletsencrypt\nproduction\n",
            file_get_contents($capturedArgs),
        );
        $this->assertSame(
            "INSTALL_SAVE_CREDS=true\nINSTALL_SSL_SWITCH_TARGET=letsencrypt\n",
            file_get_contents($capturedEnv),
        );
    }

    public function test_setup_document_points_to_single_command_entrypoint(): void
    {
        $repoRoot = dirname(__DIR__, 2);
        $contents = file_get_contents($repoRoot.'/SETUP.md');

        $this->assertIsString($contents);
        $this->assertStringContainsString('./setup.sh', $contents);
        $this->assertStringContainsString('.env` and `.env.example`', $contents);
        $this->assertStringContainsString('compose.yaml`, `compose.app.yml`, and `compose.obs.yml`', $contents);
        $this->assertStringContainsString('jobs-borads_app-plane', $contents);
        $this->assertStringContainsString('CROWDSEC_DISABLE_ONLINE_API=false', $contents);
        $this->assertStringContainsString('CROWDSEC_ENROLL_KEY', $contents);
    }

    public function test_demo_cheatsheet_matches_current_setup_entrypoint_and_runtime_container_names(): void
    {
        $repoRoot = dirname(__DIR__, 2);
        $contents = file_get_contents($repoRoot.'/DEMO_CHEATSHEET.md');

        $this->assertIsString($contents);
        $this->assertStringContainsString('./setup.sh', $contents);
        $this->assertStringContainsString('jobs-boards-nginx', $contents);
        $this->assertStringContainsString('jobs-boards-crowdsec', $contents);
        $this->assertStringContainsString('BT_APP_PLANE_NETWORK_NAME=jobs-borads_app-plane', $contents);
    }

    public function test_packaged_demo_env_uses_default_https_ports_and_explicit_app_plane_network(): void
    {
        $repoRoot = dirname(__DIR__, 2);
        $contents = file_get_contents($repoRoot.'/.env');

        $this->assertIsString($contents);
        $this->assertStringContainsString("APP_PORT=80\n", $contents);
        $this->assertStringContainsString("APP_SSL_PORT=443\n", $contents);
        $this->assertStringContainsString("BT_APP_PLANE_NETWORK_NAME=jobs-borads_app-plane\n", $contents);
        $this->assertSame(1, substr_count($contents, "CROWDSEC_DISABLE_ONLINE_API=true\n"));
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-setup-shell-'.bin2hex(random_bytes(8));
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
