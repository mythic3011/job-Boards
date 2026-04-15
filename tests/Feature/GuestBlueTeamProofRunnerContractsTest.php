<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Verification path: sqlite-safe.
 */
class GuestBlueTeamProofRunnerContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_guest_proof_runner_preserves_input_cleans_only_work_and_output_and_emits_a_fragment(): void
    {
        $sandbox = $this->makeTempDir();
        $inputDir = $sandbox.'/input';
        $workDir = $sandbox.'/workdir';
        $outputDir = $sandbox.'/output/current';
        $fakeBin = $sandbox.'/fake-bin';
        $sudoLog = $sandbox.'/sudo.log';

        mkdir($inputDir, 0777, true);
        mkdir($workDir, 0777, true);
        mkdir($outputDir, 0777, true);
        mkdir($fakeBin, 0777, true);

        file_put_contents($inputDir.'/sentinel.txt', "host-owned\n");
        file_put_contents($workDir.'/stale.txt', "remove me\n");
        file_put_contents($outputDir.'/stale.txt', "remove me\n");

        $this->installGuestProofRunner($sandbox);
        $archiveHash = $this->writeInputArchive($sandbox, $inputDir);
        $this->writeManifest($inputDir.'/manifest.json', $archiveHash);
        $this->writeExecutable($inputDir.'/guest-install-deps.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'installer\n' >> "${BT_PROOF_SEQUENCE_LOG}"
BASH);
        $this->writeFakeSha256sum($fakeBin.'/sha256sum', $archiveHash);
        $this->writeProofPrivilegeToolchain($fakeBin, $sudoLog);

        $process = $this->runProofRunner($sandbox, $inputDir, $workDir, $outputDir, $fakeBin);
        $sequence = (string) file_get_contents($outputDir.'/sequence.log');
        $fragment = $this->readJsonFile($outputDir.'/guest-fragment.json');
        $sudoOutput = (string) file_get_contents($sudoLog);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertFileExists($inputDir.'/sentinel.txt');
        $this->assertFileDoesNotExist($workDir.'/stale.txt');
        $this->assertFileDoesNotExist($outputDir.'/stale.txt');
        $this->assertFileExists($workDir.'/repo/setup-blue-team-vm.sh');
        $this->assertSame("installer\nsetup:host\nsetup:app\nsetup:obs\nsmoke\nsetup:verify\n", $sequence);
        $this->assertSame('guest_fragment', $fragment['record_type'] ?? null);
        $this->assertSame('PASS', $fragment['proof_status'] ?? null);
        $this->assertSame([
            'guest-install-deps',
            'setup-blue-team-vm.sh host',
            'setup-blue-team-vm.sh app',
            'setup-blue-team-vm.sh obs',
            'ops/smoke/run-all.sh',
            'setup-blue-team-vm.sh verify',
        ], $fragment['steps'] ?? null);
        $this->assertFileDoesNotExist($outputDir.'/result.json');
        $this->assertStringContainsString('true', $sudoOutput);
        $this->assertStringContainsString('docker info', $sudoOutput);
        $this->assertStringContainsString('setup-blue-team-vm.sh host', $sudoOutput);
        $this->assertStringContainsString('setup-blue-team-vm.sh app', $sudoOutput);
        $this->assertStringContainsString('setup-blue-team-vm.sh obs', $sudoOutput);
        $this->assertStringContainsString('setup-blue-team-vm.sh verify', $sudoOutput);
        $this->assertStringNotContainsString('ops/smoke/run-all.sh', $sudoOutput);
        $this->assertFileExists($outputDir.'/10-os-release.txt');
        $this->assertFileExists($outputDir.'/11-uname.txt');
        $this->assertFileExists($outputDir.'/12-docker-version.txt');
        $this->assertFileExists($outputDir.'/13-docker-compose-version.txt');
        $this->assertFileExists($outputDir.'/14-compose-app-ps.txt');
        $this->assertFileExists($outputDir.'/15-compose-obs-ps.txt');
        $this->assertFileExists($outputDir.'/16-systemctl-docker.txt');
    }

    public function test_guest_proof_runner_fails_on_archive_hash_mismatch_before_extract_or_execution(): void
    {
        $sandbox = $this->makeTempDir();
        $inputDir = $sandbox.'/input';
        $workDir = $sandbox.'/workdir';
        $outputDir = $sandbox.'/output/current';
        $fakeBin = $sandbox.'/fake-bin';

        mkdir($inputDir, 0777, true);
        mkdir($fakeBin, 0777, true);

        $this->installGuestProofRunner($sandbox);
        $archiveHash = $this->writeInputArchive($sandbox, $inputDir);
        $this->writeManifest($inputDir.'/manifest.json', str_repeat('a', 64));
        $this->writeExecutable($inputDir.'/guest-install-deps.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'installer\n' >> "${BT_PROOF_SEQUENCE_LOG}"
BASH);
        $this->writeFakeSha256sum($fakeBin.'/sha256sum', $archiveHash);
        $this->writeProofPrivilegeToolchain($fakeBin, $sandbox.'/sudo.log');

        $process = $this->runProofRunner($sandbox, $inputDir, $workDir, $outputDir, $fakeBin);
        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('archive_hash_mismatch', $combinedOutput);
        $this->assertFileDoesNotExist($workDir.'/repo/setup-blue-team-vm.sh');
        $this->assertFileDoesNotExist($outputDir.'/sequence.log');
    }

    public function test_guest_proof_runner_fails_fast_when_non_interactive_sudo_is_unavailable(): void
    {
        $sandbox = $this->makeTempDir();
        $inputDir = $sandbox.'/input';
        $workDir = $sandbox.'/workdir';
        $outputDir = $sandbox.'/output/current';
        $fakeBin = $sandbox.'/fake-bin';

        mkdir($inputDir, 0777, true);
        mkdir($fakeBin, 0777, true);

        $this->installGuestProofRunner($sandbox);
        $archiveHash = $this->writeInputArchive($sandbox, $inputDir);
        $this->writeManifest($inputDir.'/manifest.json', $archiveHash);
        $this->writeExecutable($inputDir.'/guest-install-deps.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'installer\n' >> "${BT_PROOF_SEQUENCE_LOG}"
BASH);
        $this->writeFakeSha256sum($fakeBin.'/sha256sum', $archiveHash);
        $this->writeProofPrivilegeToolchain($fakeBin, $sandbox.'/sudo.log', allowSudoTrue: false);

        $process = $this->runProofRunner($sandbox, $inputDir, $workDir, $outputDir, $fakeBin);
        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('sudo_non_interactive_required', $combinedOutput);
        $this->assertFileDoesNotExist($outputDir.'/sequence.log');
        $this->assertFileDoesNotExist($workDir.'/repo/setup-blue-team-vm.sh');
    }

    public function test_guest_proof_runner_fails_fast_when_docker_daemon_access_is_unavailable(): void
    {
        $sandbox = $this->makeTempDir();
        $inputDir = $sandbox.'/input';
        $workDir = $sandbox.'/workdir';
        $outputDir = $sandbox.'/output/current';
        $fakeBin = $sandbox.'/fake-bin';

        mkdir($inputDir, 0777, true);
        mkdir($fakeBin, 0777, true);

        $this->installGuestProofRunner($sandbox);
        $archiveHash = $this->writeInputArchive($sandbox, $inputDir);
        $this->writeManifest($inputDir.'/manifest.json', $archiveHash);
        $this->writeExecutable($inputDir.'/guest-install-deps.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'installer\n' >> "${BT_PROOF_SEQUENCE_LOG}"
BASH);
        $this->writeFakeSha256sum($fakeBin.'/sha256sum', $archiveHash);
        $this->writeProofPrivilegeToolchain($fakeBin, $sandbox.'/sudo.log', allowDockerInfo: false);

        $process = $this->runProofRunner($sandbox, $inputDir, $workDir, $outputDir, $fakeBin);
        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('docker_daemon_access_required', $combinedOutput);
        $this->assertSame("installer\n", (string) file_get_contents($outputDir.'/sequence.log'));
        $this->assertFileExists($workDir.'/repo/setup-blue-team-vm.sh');
        $this->assertFileDoesNotExist($outputDir.'/20-bootstrap-host.log');
    }

    public function test_guest_proof_runner_uses_sha256sum_not_shasum(): void
    {
        $scriptPath = $this->repoRoot.'/ops/proof/guest-blue-team-proof.sh';

        $this->assertFileExists($scriptPath, 'Expected guest proof runner script to exist.');

        $contents = (string) file_get_contents($scriptPath);

        $this->assertStringContainsString('sha256sum', $contents);
        $this->assertStringNotContainsString('shasum -a 256', $contents);
    }

    public function test_guest_proof_runner_emits_metadata_safe_obs_artifact_projection_without_copying_raw_secret_files(): void
    {
        $sandbox = $this->makeTempDir();
        $inputDir = $sandbox.'/input';
        $workDir = $sandbox.'/workdir';
        $outputDir = $sandbox.'/output/current';
        $fakeBin = $sandbox.'/fake-bin';
        $stateDir = $sandbox.'/blue-team-state';

        mkdir($inputDir, 0777, true);
        mkdir($fakeBin, 0777, true);
        mkdir($stateDir.'/runtime', 0777, true);
        mkdir($stateDir.'/rendered', 0777, true);

        $this->installGuestProofRunner($sandbox);
        $archiveHash = $this->writeInputArchive($sandbox, $inputDir);
        $this->writeManifest($inputDir.'/manifest.json', $archiveHash);
        $this->writeExecutable($inputDir.'/guest-install-deps.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'installer\n' >> "${BT_PROOF_SEQUENCE_LOG}"
BASH);
        $this->writeFakeSha256sum($fakeBin.'/sha256sum', $archiveHash);
        $this->writeProofPrivilegeToolchain($fakeBin, $sandbox.'/sudo.log');

        file_put_contents($stateDir.'/runtime/grafana-admin-secret', "not-for-export\n");
        file_put_contents($stateDir.'/rendered/prometheus.web-config.yml', "basic_auth_users:\n  admin: \"hidden\"\n");
        file_put_contents(
            $stateDir.'/runtime/obs.generated.env',
            implode("\n", [
                'SESSION_SECRET=session-fixture-marker',
                'MONITORING_PASSWORD_HASH=$2y$12$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'PROMETHEUS_PASSWORD_HASH=$2y$12$bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                'GRAFANA_SECRET_FILE='.$stateDir.'/runtime/grafana-admin-secret',
                'PROMETHEUS_WEB_CONFIG_FILE='.$stateDir.'/rendered/prometheus.web-config.yml',
            ])."\n",
        );
        file_put_contents($stateDir.'/runtime/obs.generated-secrets.jsonl', implode("\n", [
            '{"record_type":"generated_secret","generated_at":"2026-04-14T00:00:00Z","generated_by":"blue-team-bootstrap","target_field":"SESSION_SECRET","source_field":"random","mode":"generated_secret","deterministic":false,"user_action_required":false}',
            '{"record_type":"generated_secret","generated_at":"2026-04-14T00:00:01Z","generated_by":"blue-team-bootstrap","target_field":"GRAFANA_SECRET_FILE","source_field":"GRAFANA_PASSWORD","mode":"materialized_secret_file","deterministic":true,"user_action_required":false}',
        ])."\n");

        $process = $this->runProofRunner(
            $sandbox,
            $inputDir,
            $workDir,
            $outputDir,
            $fakeBin,
            ['BT_STATE_DIR' => $stateDir],
        );

        $projectionPath = $outputDir.'/obs-runtime-metadata.json';
        $projection = $this->readJsonFile($projectionPath);
        $serializedProjection = (string) file_get_contents($projectionPath);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame('obs_runtime_metadata', $projection['record_type'] ?? null);
        $this->assertSame('present', $projection['generated_env']['status'] ?? null);
        $this->assertSame('present', $projection['generated_secret_audit']['status'] ?? null);
        $this->assertTrue($projection['generated_env']['keys']['SESSION_SECRET']['present'] ?? false);
        $this->assertSame('grafana-admin-secret', $projection['generated_env']['keys']['GRAFANA_SECRET_FILE']['basename'] ?? null);
        $this->assertTrue($projection['generated_env']['keys']['PROMETHEUS_WEB_CONFIG_FILE']['readable'] ?? false);
        $this->assertSame(2, $projection['generated_secret_audit']['record_count'] ?? null);
        $this->assertSame('SESSION_SECRET', $projection['generated_secret_audit']['records'][0]['target_field'] ?? null);
        $this->assertStringNotContainsString('session-fixture-marker', $serializedProjection);
        $this->assertStringNotContainsString('not-for-export', $serializedProjection);
        $this->assertFileDoesNotExist($outputDir.'/obs.generated.env');
        $this->assertFileDoesNotExist($outputDir.'/obs.generated-secrets.jsonl');
        $this->assertFileDoesNotExist($outputDir.'/grafana-admin-secret');
    }

    public function test_guest_proof_runner_executes_smoke_from_repo_root_with_explicit_paths_and_non_root_docker_context(): void
    {
        $sandbox = $this->makeTempDir();
        $inputDir = $sandbox.'/input';
        $workDir = $sandbox.'/workdir';
        $outputDir = $sandbox.'/output/current';
        $fakeBin = $sandbox.'/fake-bin';

        mkdir($inputDir, 0777, true);
        mkdir($fakeBin, 0777, true);

        $this->installGuestProofRunner($sandbox);
        $archiveHash = $this->writeInputArchiveWithSmokeContract($sandbox, $inputDir);
        $this->writeManifest($inputDir.'/manifest.json', $archiveHash);
        $this->writeExecutable($inputDir.'/guest-install-deps.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'installer\n' >> "${BT_PROOF_SEQUENCE_LOG}"
BASH);
        $this->writeFakeSha256sum($fakeBin.'/sha256sum', $archiveHash);
        $this->writeProofPrivilegeToolchainWithDockerGroupFallback($fakeBin, $sandbox.'/sudo.log', $workDir.'/repo');

        $process = $this->runProofRunner($sandbox, $inputDir, $workDir, $outputDir, $fakeBin);
        $sequence = (string) file_get_contents($outputDir.'/sequence.log');
        $smokeContext = $this->readJsonFile($outputDir.'/smoke-context.json');
        $expectedRepoDir = (string) realpath($workDir.'/repo');

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame("installer\nsetup:host\nsetup:app\nsetup:obs\nsmoke\nsetup:verify\n", $sequence);
        $this->assertSame($expectedRepoDir, $smokeContext['pwd'] ?? null);
        $this->assertSame((string) realpath($expectedRepoDir.'/setup-blue-team-vm.sh'), realpath((string) ($smokeContext['runner'] ?? '')));
        $this->assertSame((string) realpath($expectedRepoDir.'/compose.app.yml'), realpath((string) ($smokeContext['app_compose_file'] ?? '')));
        $this->assertSame((string) realpath($expectedRepoDir.'/compose.obs.yml'), realpath((string) ($smokeContext['obs_compose_file'] ?? '')));
        $this->assertSame('sg', $smokeContext['docker_context'] ?? null);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-guest-proof-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
    }

    private function installGuestProofRunner(string $sandbox): void
    {
        $scriptPath = $this->repoRoot.'/ops/proof/guest-blue-team-proof.sh';
        $this->assertFileExists($scriptPath, 'Expected guest proof runner script to exist.');
        $this->writeExecutable(
            $sandbox.'/ops/proof/guest-blue-team-proof.sh',
            (string) file_get_contents($scriptPath),
        );
    }

    private function writeInputArchive(string $sandbox, string $inputDir): string
    {
        $repoDir = $sandbox.'/repo-src';
        mkdir($repoDir.'/ops/smoke', 0777, true);
        mkdir($repoDir.'/ops/lib', 0777, true);
        file_put_contents($repoDir.'/compose.app.yml', "services: {}\n");
        file_put_contents($repoDir.'/compose.obs.yml', "services: {}\n");
        $this->writeExecutable($repoDir.'/ops/lib/common.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
bt_compose() {
  local compose_file="$1"
  shift
  docker compose -f "${compose_file}" "$@"
}
BASH);

        $this->writeExecutable($repoDir.'/setup-blue-team-vm.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'setup:%s\n' "${1:-}" >> "${BT_PROOF_SEQUENCE_LOG}"
BASH);
        $this->writeExecutable($repoDir.'/ops/smoke/run-all.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'smoke\n' >> "${BT_PROOF_SEQUENCE_LOG}"
BASH);

        $archivePath = $inputDir.'/repo.tgz';
        $process = new Process(['tar', '-czf', $archivePath, '-C', $repoDir, '.']);
        $process->mustRun();

        return hash_file('sha256', $archivePath);
    }

    private function writeInputArchiveWithSmokeContract(string $sandbox, string $inputDir): string
    {
        $repoDir = $sandbox.'/repo-src-smoke-contract';
        mkdir($repoDir.'/ops/smoke', 0777, true);
        mkdir($repoDir.'/ops/lib', 0777, true);
        file_put_contents($repoDir.'/compose.app.yml', "services: {}\n");
        file_put_contents($repoDir.'/compose.obs.yml', "services: {}\n");
        $this->writeExecutable($repoDir.'/ops/lib/common.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
bt_compose() {
  local compose_file="$1"
  shift
  docker compose -f "${compose_file}" "$@"
}
BASH);

        $this->writeExecutable($repoDir.'/setup-blue-team-vm.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'setup:%s\n' "${1:-}" >> "${BT_PROOF_SEQUENCE_LOG}"
BASH);
        $this->writeExecutable($repoDir.'/ops/smoke/run-all.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
[[ "$(pwd)" == "${BT_EXPECTED_REPO_DIR}" ]] || exit 71
[[ "${RUNNER:-}" == "${BT_EXPECTED_REPO_DIR}/setup-blue-team-vm.sh" ]] || exit 72
[[ "${APP_COMPOSE_FILE:-}" == "${BT_EXPECTED_REPO_DIR}/compose.app.yml" ]] || exit 73
[[ "${OBS_COMPOSE_FILE:-}" == "${BT_EXPECTED_REPO_DIR}/compose.obs.yml" ]] || exit 74
docker info >/dev/null
python3 - <<'PY'
import json
import os
payload = {
    "pwd": os.getcwd(),
    "runner": os.environ.get("RUNNER"),
    "app_compose_file": os.environ.get("APP_COMPOSE_FILE"),
    "obs_compose_file": os.environ.get("OBS_COMPOSE_FILE"),
    "docker_context": os.environ.get("BT_FAKE_DOCKER_CONTEXT"),
}
with open(os.environ["BT_PROOF_OUTPUT_DIR"] + "/smoke-context.json", "w", encoding="utf-8") as handle:
    json.dump(payload, handle, indent=2, sort_keys=True)
    handle.write("\n")
PY
printf 'smoke\n' >> "${BT_PROOF_SEQUENCE_LOG}"
BASH);

        $archivePath = $inputDir.'/repo.tgz';
        $process = new Process(['tar', '-czf', $archivePath, '-C', $repoDir, '.']);
        $process->mustRun();

        return hash_file('sha256', $archivePath);
    }

    private function writeManifest(string $path, string $archiveHash): void
    {
        file_put_contents($path, json_encode([
            'archive_sha256' => $archiveHash,
        ], JSON_THROW_ON_ERROR));
    }

    private function writeFakeSha256sum(string $path, string $archiveHash): void
    {
        $this->writeExecutable($path, <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s  %s\n' "{$archiveHash}" "\${1:-}"
BASH);
    }

    private function writeProofPrivilegeToolchain(
        string $binDir,
        string $sudoLogPath,
        bool $allowSudoTrue = true,
        bool $allowDockerInfo = true,
    ): void {
        $allowSudoTrueFlag = $allowSudoTrue ? '1' : '0';
        $allowDockerInfoFlag = $allowDockerInfo ? '1' : '0';

        $this->writeExecutable($binDir.'/sudo', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$sudoLogPath}"
if [[ "\${1:-}" != "-n" ]]; then
  exit 91
fi
shift
if [[ "{$allowSudoTrueFlag}" != "1" && "\${1:-}" == "true" ]]; then
  exit 92
fi
"\$@"
BASH);

        $this->writeExecutable($binDir.'/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
case "\${1:-}" in
  info)
    if [[ "{$allowDockerInfoFlag}" != "1" ]]; then
      exit 93
    fi
    printf 'Server: Docker Engine\\n'
    exit 0
    ;;
  version)
    printf 'Docker version 26.1.0\\n'
    exit 0
    ;;
  compose)
    if [[ "\${2:-}" == "version" ]]; then
      printf 'Docker Compose version v2.27.0\\n'
      exit 0
    fi
    if [[ "\${2:-}" == "-f" && "\${4:-}" == "ps" ]]; then
      printf 'NAME STATUS\\nproof running\\n'
      exit 0
    fi
    exit 0
    ;;
esac
exit 0
BASH);

        $this->writeExecutable($binDir.'/systemctl', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "status" && "${2:-}" == "docker" && "${3:-}" == "--no-pager" ]]; then
  printf 'docker.service - Docker Application Container Engine\n'
  exit 0
fi
exit 0
BASH);

        $this->writeExecutable($binDir.'/uname', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'Linux clean-vm 5.15.0-test #1 SMP\n'
BASH);

        $this->writeExecutable($binDir.'/cat', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "/etc/os-release" ]]; then
  printf 'NAME="Ubuntu"\nVERSION="22.04.5 LTS"\n'
  exit 0
fi
/bin/cat "$@"
BASH);
    }

    private function writeProofPrivilegeToolchainWithDockerGroupFallback(string $binDir, string $sudoLogPath, string $expectedRepoDir): void
    {
        $this->writeExecutable($binDir.'/sudo', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$sudoLogPath}"
if [[ "\${1:-}" != "-n" ]]; then
  exit 91
fi
shift
BT_FAKE_DOCKER_CONTEXT=sudo BT_EXPECTED_REPO_DIR="{$expectedRepoDir}" "\$@"
BASH);

        $this->writeExecutable($binDir.'/sg', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
group_name="\${1:-}"
flag="\${2:-}"
command_string="\${3:-}"
[[ "\${group_name}" == "docker" ]] || exit 95
[[ "\${flag}" == "-c" ]] || exit 96
BT_FAKE_DOCKER_CONTEXT=sg BT_EXPECTED_REPO_DIR="{$expectedRepoDir}" bash -c "\${command_string}"
BASH);

        $this->writeExecutable($binDir.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
case "${1:-}" in
  info)
    if [[ "${BT_FAKE_DOCKER_CONTEXT:-}" != "sudo" && "${BT_FAKE_DOCKER_CONTEXT:-}" != "sg" ]]; then
      exit 93
    fi
    printf 'Server: Docker Engine\n'
    exit 0
    ;;
  version)
    printf 'Docker version 26.1.0\n'
    exit 0
    ;;
  compose)
    if [[ "${2:-}" == "version" ]]; then
      printf 'Docker Compose version v2.27.0\n'
      exit 0
    fi
    if [[ "${2:-}" == "-f" && "${4:-}" == "ps" ]]; then
      printf 'NAME STATUS\nproof running\n'
      exit 0
    fi
    exit 0
    ;;
esac
exit 0
BASH);

        $this->writeExecutable($binDir.'/systemctl', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "status" && "${2:-}" == "docker" && "${3:-}" == "--no-pager" ]]; then
  printf 'docker.service - Docker Application Container Engine\n'
  exit 0
fi
exit 0
BASH);

        $this->writeExecutable($binDir.'/uname', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'Linux clean-vm 5.15.0-test #1 SMP\n'
BASH);

        $this->writeExecutable($binDir.'/cat', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "/etc/os-release" ]]; then
  printf 'NAME="Ubuntu"\nVERSION="22.04.5 LTS"\n'
  exit 0
fi
/bin/cat "$@"
BASH);
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
     * @param array<string, string> $extraEnv
     */
    private function runProofRunner(string $sandbox, string $inputDir, string $workDir, string $outputDir, string $fakeBin, array $extraEnv = []): Process
    {
        $process = new Process(
            [$sandbox.'/ops/proof/guest-blue-team-proof.sh'],
            $sandbox,
            array_merge([
                'PATH' => $fakeBin.':/usr/bin:/bin',
                'BT_PROOF_INPUT_DIR' => $inputDir,
                'BT_PROOF_WORKDIR' => $workDir,
                'BT_PROOF_OUTPUT_DIR' => $outputDir,
            ], $extraEnv),
            null,
            20,
        );
        $process->run();

        return $process;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path): array
    {
        $this->assertFileExists($path);
        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
