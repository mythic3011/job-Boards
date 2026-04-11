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
        $grafanaPasswordFile = $tempDir.'/grafana-admin-password';
        file_put_contents($grafanaPasswordFile, "grafana-secret\n");
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
                'BT_GRAFANA_PASSWORD_FILE' => $grafanaPasswordFile,
                'MONITORING_ADMIN_USERNAME' => 'admin',
                'MONITORING_PASSWORD_HASH' => 'not-a-valid-bcrypt',
                'SESSION_SECRET' => str_repeat('a', 64),
                'GRAFANA_PASSWORD_FILE' => $grafanaPasswordFile,
                'PROMETHEUS_PASSWORD_HASH' => password_hash('prometheus-secret', PASSWORD_BCRYPT),
            ],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('"check_id":"obs.bootstrap.required_env"', $combinedOutput);
        $this->assertStringContainsString('"status":"FAIL"', $combinedOutput);
        $this->assertStringNotContainsString('compose -f', @file_get_contents($dockerLog) ?: '');
    }

    public function test_obs_apply_fails_before_compose_when_prometheus_runtime_config_cannot_be_rendered(): void
    {
        $tempDir = $this->makeTempDir();
        $dockerLog = $tempDir.'/docker.log';
        $fakeBin = $this->makeFakeDockerBin($tempDir, $dockerLog);
        $grafanaPasswordFile = $tempDir.'/grafana-admin-password';
        file_put_contents($grafanaPasswordFile, "grafana-secret\n");
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
                'BT_GRAFANA_PASSWORD_FILE' => $grafanaPasswordFile,
                'MONITORING_ADMIN_USERNAME' => 'admin',
                'MONITORING_PASSWORD_HASH' => password_hash('monitoring-secret', PASSWORD_BCRYPT),
                'SESSION_SECRET' => str_repeat('b', 64),
                'GRAFANA_PASSWORD_FILE' => $grafanaPasswordFile,
                'PROMETHEUS_PASSWORD_HASH' => password_hash('prometheus-secret', PASSWORD_BCRYPT),
            ],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('"check_id":"obs.bootstrap.required_env"', $combinedOutput);
        $this->assertStringContainsString('"status":"FAIL"', $combinedOutput);
        $this->assertStringNotContainsString('compose -f', @file_get_contents($dockerLog) ?: '');
    }

    public function test_obs_compose_requires_explicit_prometheus_runtime_web_config_path(): void
    {
        $contents = file_get_contents($this->repoRoot.'/compose.obs.yml');

        $this->assertIsString($contents);
        $this->assertStringContainsString('${PROMETHEUS_WEB_CONFIG_FILE:?Set PROMETHEUS_WEB_CONFIG_FILE before obs apply}', $contents);
        $this->assertStringNotContainsString('${PROMETHEUS_WEB_CONFIG_FILE:-./docker/prometheus/web-config.yml}', $contents);
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
        $this->assertSame('overall_summary', $overallSummary['record_type'] ?? null);
        $this->assertSame('overall', $overallSummary['plane'] ?? null);
        $this->assertSame('FAIL', $overallSummary['status'] ?? null);
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
    private function findJsonRecord(string $output, string $recordType, string $plane): array
    {
        foreach (preg_split('/\R/', $output) as $line) {
            if ($line === '') {
                continue;
            }

            $record = json_decode($line, true);
            if (! is_array($record)) {
                continue;
            }

            if (($record['record_type'] ?? null) === $recordType && ($record['plane'] ?? null) === $plane) {
                return $record;
            }
        }

        $this->fail("Did not find {$recordType} record for plane {$plane}");
    }
}
