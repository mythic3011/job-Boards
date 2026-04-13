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

        $process = $this->runProofRunner($sandbox, $inputDir, $workDir, $outputDir, $fakeBin);
        $sequence = (string) file_get_contents($outputDir.'/sequence.log');
        $fragment = $this->readJsonFile($outputDir.'/guest-fragment.json');

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

        $process = $this->runProofRunner($sandbox, $inputDir, $workDir, $outputDir, $fakeBin);
        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('archive_hash_mismatch', $combinedOutput);
        $this->assertFileDoesNotExist($workDir.'/repo/setup-blue-team-vm.sh');
        $this->assertFileDoesNotExist($outputDir.'/sequence.log');
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

        file_put_contents($stateDir.'/runtime/grafana-admin-password', "not-for-export\n");
        file_put_contents($stateDir.'/rendered/prometheus.web-config.yml', "basic_auth_users:\n  admin: \"hidden\"\n");
        file_put_contents(
            $stateDir.'/runtime/obs.generated.env',
            implode("\n", [
                'SESSION_SECRET=super-secret-session',
                'MONITORING_PASSWORD_HASH=$2y$12$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'PROMETHEUS_PASSWORD_HASH=$2y$12$bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                'GRAFANA_PASSWORD_FILE='.$stateDir.'/runtime/grafana-admin-password',
                'PROMETHEUS_WEB_CONFIG_FILE='.$stateDir.'/rendered/prometheus.web-config.yml',
            ])."\n",
        );
        file_put_contents($stateDir.'/runtime/obs.generated-secrets.jsonl', implode("\n", [
            '{"record_type":"generated_secret","generated_at":"2026-04-14T00:00:00Z","generated_by":"blue-team-bootstrap","target_field":"SESSION_SECRET","source_field":"random","mode":"generated_secret","deterministic":false,"user_action_required":false}',
            '{"record_type":"generated_secret","generated_at":"2026-04-14T00:00:01Z","generated_by":"blue-team-bootstrap","target_field":"GRAFANA_PASSWORD_FILE","source_field":"GRAFANA_PASSWORD","mode":"materialized_secret_file","deterministic":true,"user_action_required":false}',
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
        $this->assertSame('grafana-admin-password', $projection['generated_env']['keys']['GRAFANA_PASSWORD_FILE']['basename'] ?? null);
        $this->assertTrue($projection['generated_env']['keys']['PROMETHEUS_WEB_CONFIG_FILE']['readable'] ?? false);
        $this->assertSame(2, $projection['generated_secret_audit']['record_count'] ?? null);
        $this->assertSame('SESSION_SECRET', $projection['generated_secret_audit']['records'][0]['target_field'] ?? null);
        $this->assertStringNotContainsString('super-secret-session', $serializedProjection);
        $this->assertStringNotContainsString('not-for-export', $serializedProjection);
        $this->assertFileDoesNotExist($outputDir.'/obs.generated.env');
        $this->assertFileDoesNotExist($outputDir.'/obs.generated-secrets.jsonl');
        $this->assertFileDoesNotExist($outputDir.'/grafana-admin-password');
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
