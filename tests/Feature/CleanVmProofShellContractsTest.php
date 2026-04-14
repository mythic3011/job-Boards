<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Verification path: sqlite-safe.
 */
class CleanVmProofShellContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_host_proof_requires_a_clean_commit_only_workspace_before_archiving(): void
    {
        $tempRoot = $this->makeTempDir();
        $sandbox = $this->makeTempDir();
        $prlctlLog = $sandbox.'/prlctl.log';
        $fakeBin = $sandbox.'/fake-bin';
        $outputDir = $sandbox.'/artifacts';

        $this->installHostProofScript($tempRoot);
        $this->initializeGitRepo($tempRoot);
        mkdir($fakeBin, 0777, true);

        file_put_contents($tempRoot.'/tracked.txt', "dirty\n");
        $this->writeFakePrlctl($fakeBin, $prlctlLog, json_encode([
            ['name' => 'base-os-ssh', 'id' => '{snapshot-1}'],
        ], JSON_THROW_ON_ERROR));

        $process = $this->runHostProof(
            $tempRoot,
            $fakeBin,
            [
                '--vm-name', 'Ubuntu Server 22.04.5 LTS-test-cleanvm',
                '--snapshot-name', 'base-os-ssh',
                '--ssh-host', '192.0.2.10',
                '--ssh-user', 'ubuntu',
                '--output-dir', $outputDir,
                '--run-id', 'slice-a-dirty',
                '--dry-run',
            ],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $result = $this->readJsonFile($outputDir.'/slice-a-dirty/result.json');

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('dirty_worktree', $combinedOutput);
        $this->assertSame('dirty_worktree', $result['primary_failure_code'] ?? null);
        $this->assertSame('preflight', $result['primary_failure_phase'] ?? null);
        $this->assertSame('', @file_get_contents($prlctlLog) ?: '');
    }

    public function test_host_proof_fails_when_snapshot_name_is_missing(): void
    {
        $tempRoot = $this->makeTempDir();
        $sandbox = $this->makeTempDir();
        $prlctlLog = $sandbox.'/prlctl.log';
        $fakeBin = $sandbox.'/fake-bin';
        $outputDir = $sandbox.'/artifacts';

        $this->installHostProofScript($tempRoot);
        $this->initializeGitRepo($tempRoot);
        mkdir($fakeBin, 0777, true);

        $this->writeFakePrlctl($fakeBin, $prlctlLog, json_encode([], JSON_THROW_ON_ERROR));

        $process = $this->runHostProof(
            $tempRoot,
            $fakeBin,
            [
                '--vm-name', 'Ubuntu Server 22.04.5 LTS-test-cleanvm',
                '--snapshot-name', 'base-os-ssh',
                '--ssh-host', '192.0.2.10',
                '--ssh-user', 'ubuntu',
                '--output-dir', $outputDir,
                '--run-id', 'slice-a-missing',
                '--dry-run',
            ],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $result = $this->readJsonFile($outputDir.'/slice-a-missing/result.json');

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('snapshot_missing', $combinedOutput);
        $this->assertSame('snapshot_missing', $result['primary_failure_code'] ?? null);
        $this->assertSame('preflight', $result['primary_failure_phase'] ?? null);
        $this->assertStringContainsString('snapshot-list', (string) @file_get_contents($prlctlLog));
    }

    public function test_host_proof_fails_when_snapshot_name_is_ambiguous(): void
    {
        $tempRoot = $this->makeTempDir();
        $sandbox = $this->makeTempDir();
        $prlctlLog = $sandbox.'/prlctl.log';
        $fakeBin = $sandbox.'/fake-bin';
        $outputDir = $sandbox.'/artifacts';

        $this->installHostProofScript($tempRoot);
        $this->initializeGitRepo($tempRoot);
        mkdir($fakeBin, 0777, true);

        $this->writeFakePrlctl($fakeBin, $prlctlLog, json_encode([
            ['name' => 'base-os-ssh', 'id' => '{snapshot-1}'],
            ['name' => 'base-os-ssh', 'id' => '{snapshot-2}'],
        ], JSON_THROW_ON_ERROR));

        $process = $this->runHostProof(
            $tempRoot,
            $fakeBin,
            [
                '--vm-name', 'Ubuntu Server 22.04.5 LTS-test-cleanvm',
                '--snapshot-name', 'base-os-ssh',
                '--ssh-host', '192.0.2.10',
                '--ssh-user', 'ubuntu',
                '--output-dir', $outputDir,
                '--run-id', 'slice-a-ambiguous',
                '--dry-run',
            ],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $result = $this->readJsonFile($outputDir.'/slice-a-ambiguous/result.json');

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('snapshot_ambiguous', $combinedOutput);
        $this->assertSame('snapshot_ambiguous', $result['primary_failure_code'] ?? null);
        $this->assertSame('preflight', $result['primary_failure_phase'] ?? null);
        $this->assertStringContainsString('snapshot-list', (string) @file_get_contents($prlctlLog));
    }

    public function test_host_proof_dry_run_records_commit_archive_manifest_and_host_owned_result(): void
    {
        $tempRoot = $this->makeTempDir();
        $sandbox = $this->makeTempDir();
        $prlctlLog = $sandbox.'/prlctl.log';
        $fakeBin = $sandbox.'/fake-bin';
        $outputDir = $sandbox.'/artifacts';

        $this->installHostProofScript($tempRoot);
        $headCommit = $this->initializeGitRepo($tempRoot);
        mkdir($fakeBin, 0777, true);

        $this->writeFakePrlctl($fakeBin, $prlctlLog, json_encode([
            ['name' => 'base-os-ssh', 'id' => '{snapshot-1}'],
        ], JSON_THROW_ON_ERROR));

        mkdir($outputDir.'/slice-a-green', 0777, true);
        file_put_contents(
            $outputDir.'/slice-a-green/result.json',
            json_encode(['proof_status' => 'guest-owned'], JSON_THROW_ON_ERROR)
        );

        $process = $this->runHostProof(
            $tempRoot,
            $fakeBin,
            [
                '--vm-name', 'Ubuntu Server 22.04.5 LTS-test-cleanvm',
                '--snapshot-name', 'base-os-ssh',
                '--ssh-host', '192.0.2.10',
                '--ssh-user', 'ubuntu',
                '--output-dir', $outputDir,
                '--run-id', 'slice-a-green',
                '--repo-ref', 'HEAD',
                '--dry-run',
            ],
        );

        $manifest = $this->readJsonFile($outputDir.'/slice-a-green/manifest.json');
        $result = $this->readJsonFile($outputDir.'/slice-a-green/result.json');
        $archivePath = $outputDir.'/slice-a-green/repo.tgz';

        $this->assertSame(0, $process->getExitCode());
        $this->assertSame('HEAD', $manifest['repo_ref'] ?? null);
        $this->assertSame($headCommit, $manifest['resolved_commit_sha'] ?? null);
        $this->assertSame('slice-a-green', $manifest['run_id'] ?? null);
        $this->assertSame('{snapshot-1}', $manifest['snapshot_id'] ?? null);
        $this->assertSame('repo.tgz', $manifest['archive_filename'] ?? null);
        $this->assertArrayHasKey('archive_sha256', $manifest);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) ($manifest['archive_sha256'] ?? ''));
        $this->assertFileExists($archivePath);
        $this->assertSame('slice-a-green', $result['run_id'] ?? null);
        $this->assertSame('{snapshot-1}', $result['snapshot_id'] ?? null);
        $this->assertSame($manifest['archive_sha256'] ?? null, $result['archive_sha256'] ?? null);
        $this->assertSame('HEAD', $result['repo_ref'] ?? null);
        $this->assertSame($headCommit, $result['resolved_commit_sha'] ?? null);
        $this->assertSame($outputDir.'/slice-a-green', $result['artifact_dir'] ?? null);
        $this->assertSame('SKIPPED', $result['proof_status'] ?? null);
        $this->assertSame('SKIPPED', $result['artifact_status'] ?? null);
        $this->assertSame('SKIPPED', $result['restore_status'] ?? null);
        $this->assertSame('SKIPPED', $result['overall_status'] ?? null);
        $this->assertSame('tofu', $result['ssh_identity_mode'] ?? null);
        $this->assertSame('operational', $result['assurance_level'] ?? null);
        $this->assertStringContainsString('snapshot-list', (string) @file_get_contents($prlctlLog));
    }

    public function test_host_proof_default_output_dir_stays_outside_the_repo_worktree(): void
    {
        $tempRoot = $this->makeTempDir();
        $sandbox = $this->makeTempDir();
        $prlctlLog = $sandbox.'/prlctl.log';
        $fakeBin = $sandbox.'/fake-bin';
        $tmpDir = $sandbox.'/tmp';

        $this->installHostProofScript($tempRoot);
        $this->initializeGitRepo($tempRoot);
        mkdir($fakeBin, 0777, true);
        mkdir($tmpDir, 0777, true);

        $this->writeFakePrlctl($fakeBin, $prlctlLog, json_encode([
            ['name' => 'base-os-ssh', 'id' => '{snapshot-1}'],
        ], JSON_THROW_ON_ERROR));

        $process = $this->runHostProof(
            $tempRoot,
            $fakeBin,
            [
                '--vm-name', 'Ubuntu Server 22.04.5 LTS-test-cleanvm',
                '--snapshot-name', 'base-os-ssh',
                '--ssh-host', '192.0.2.10',
                '--ssh-user', 'ubuntu',
                '--run-id', 'slice-a-default',
                '--dry-run',
            ],
            ['TMPDIR' => $tmpDir],
        );

        $result = $this->readJsonFile($tmpDir.'/jobs-boards-clean-vm-proof/slice-a-default/result.json');
        $gitStatus = trim($this->mustRun(['git', 'status', '--porcelain', '--ignore-submodules=none'], $tempRoot)->getOutput());

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame($tmpDir.'/jobs-boards-clean-vm-proof/slice-a-default', $result['artifact_dir'] ?? null);
        $this->assertSame('', $gitStatus);
        $this->assertDirectoryDoesNotExist($tempRoot.'/.blue-team-proof');
    }

    public function test_host_proof_tofu_run_uses_non_interactive_ssh_and_collects_guest_output(): void
    {
        $tempRoot = $this->makeTempDir();
        $sandbox = $this->makeTempDir();
        $fakeBin = $sandbox.'/fake-bin';
        $remoteRoot = $sandbox.'/remote-proof';
        $outputDir = $sandbox.'/artifacts';
        $prlctlLog = $sandbox.'/prlctl.log';
        $sshLog = $sandbox.'/ssh.log';
        $scpLog = $sandbox.'/scp.log';

        $this->installHostProofScript($tempRoot);
        $headCommit = $this->initializeRuntimeProofRepo($tempRoot, 'success');
        mkdir($fakeBin, 0777, true);
        mkdir($remoteRoot, 0777, true);

        $this->writeFakeRuntimePrlctl($fakeBin.'/prlctl', $prlctlLog, json_encode([
            ['name' => 'base-os-ssh', 'id' => '{snapshot-1}'],
        ], JSON_THROW_ON_ERROR));
        $this->writeFakeRuntimeSsh($fakeBin.'/ssh', $sshLog);
        $this->writeFakeRuntimeScp($fakeBin.'/scp', $scpLog);
        $this->writeProofPrivilegeToolchain($fakeBin, $sandbox.'/sudo.log');

        $process = $this->runHostProof(
            $tempRoot,
            $fakeBin,
            [
                '--vm-name', 'Ubuntu Server 22.04.5 LTS-test-cleanvm',
                '--snapshot-name', 'base-os-ssh',
                '--ssh-host', '192.0.2.10',
                '--ssh-user', 'ubuntu',
                '--remote-proof-root', $remoteRoot,
                '--output-dir', $outputDir,
                '--run-id', 'slice-d-tofu',
            ],
            [
                'BT_FAKE_REMOTE_PATH' => $fakeBin.':/usr/bin:/bin',
                'BT_SSH_WAIT_ATTEMPTS' => '1',
                'BT_SSH_WAIT_DELAY_SECONDS' => '0',
            ],
        );

        $result = $this->readJsonFile($outputDir.'/slice-d-tofu/result.json');
        $manifest = $this->readJsonFile($outputDir.'/slice-d-tofu/manifest.json');
        $sshOutput = (string) file_get_contents($sshLog);
        $scpOutput = (string) file_get_contents($scpLog);
        $prlctlOutput = (string) file_get_contents($prlctlLog);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame('PASS', $result['proof_status'] ?? null);
        $this->assertSame('PASS', $result['artifact_status'] ?? null);
        $this->assertSame('PASS', $result['restore_status'] ?? null);
        $this->assertSame('PASS', $result['overall_status'] ?? null);
        $this->assertSame('tofu', $result['ssh_identity_mode'] ?? null);
        $this->assertSame('operational', $result['assurance_level'] ?? null);
        $this->assertSame($headCommit, $result['resolved_commit_sha'] ?? null);
        $this->assertSame($manifest['archive_sha256'] ?? null, $result['archive_sha256'] ?? null);
        $this->assertFileExists($outputDir.'/slice-d-tofu/guest-output/guest-fragment.json');
        $this->assertFileExists($outputDir.'/slice-d-tofu/guest-output/10-os-release.txt');
        $this->assertFileExists($outputDir.'/slice-d-tofu/guest-output/11-uname.txt');
        $this->assertFileExists($outputDir.'/slice-d-tofu/guest-output/12-docker-version.txt');
        $this->assertFileExists($outputDir.'/slice-d-tofu/guest-output/13-docker-compose-version.txt');
        $this->assertFileExists($outputDir.'/slice-d-tofu/guest-output/14-compose-app-ps.txt');
        $this->assertFileExists($outputDir.'/slice-d-tofu/guest-output/15-compose-obs-ps.txt');
        $this->assertFileExists($outputDir.'/slice-d-tofu/guest-output/16-systemctl-docker.txt');
        $this->assertStringContainsString('BatchMode=yes', $sshOutput);
        $this->assertStringContainsString('StrictHostKeyChecking=accept-new', $sshOutput);
        $this->assertStringContainsString('UserKnownHostsFile='.$outputDir.'/slice-d-tofu/ssh-known_hosts', $sshOutput);
        $this->assertStringContainsString($remoteRoot.'/input/guest-blue-team-proof.sh', $sshOutput);
        $this->assertStringNotContainsString('&& '.$remoteRoot.'/input/guest-install-deps.sh', $sshOutput);
        $this->assertStringContainsString('ubuntu@192.0.2.10:'.$remoteRoot.'/input/repo.tgz', $scpOutput);
        $this->assertStringContainsString('snapshot-switch Ubuntu Server 22.04.5 LTS-test-cleanvm --id {snapshot-1} --skip-resume', $prlctlOutput);
        $this->assertStringContainsString('start Ubuntu Server 22.04.5 LTS-test-cleanvm', $prlctlOutput);
        $this->assertStringContainsString('stop Ubuntu Server 22.04.5 LTS-test-cleanvm --kill', $prlctlOutput);
    }

    public function test_host_proof_stages_guest_helpers_from_the_resolved_repo_ref_not_the_current_workspace(): void
    {
        $tempRoot = $this->makeTempDir();
        $sandbox = $this->makeTempDir();
        $fakeBin = $sandbox.'/fake-bin';
        $remoteRoot = $sandbox.'/remote-proof';
        $outputDir = $sandbox.'/artifacts';
        $prlctlLog = $sandbox.'/prlctl.log';

        $this->installHostProofScript($tempRoot);
        file_put_contents($tempRoot.'/ops/proof/guest-install-deps.sh', (string) file_get_contents($tempRoot.'/ops/proof/guest-install-deps.sh')."\n# helper-version: ref-v1\n");
        file_put_contents($tempRoot.'/ops/proof/guest-blue-team-proof.sh', (string) file_get_contents($tempRoot.'/ops/proof/guest-blue-team-proof.sh')."\n# helper-version: ref-v1\n");
        $firstCommit = $this->initializeRuntimeProofRepo($tempRoot, 'success');

        file_put_contents($tempRoot.'/ops/proof/guest-install-deps.sh', (string) file_get_contents($tempRoot.'/ops/proof/guest-install-deps.sh')."# helper-version: workspace-v2\n");
        file_put_contents($tempRoot.'/ops/proof/guest-blue-team-proof.sh', (string) file_get_contents($tempRoot.'/ops/proof/guest-blue-team-proof.sh')."# helper-version: workspace-v2\n");
        $this->mustRun(['git', 'add', 'ops/proof/guest-install-deps.sh', 'ops/proof/guest-blue-team-proof.sh'], $tempRoot);
        $this->mustRun(['git', 'commit', '-m', 'Change proof helper markers'], $tempRoot);

        mkdir($fakeBin, 0777, true);
        mkdir($remoteRoot, 0777, true);

        $this->writeFakeRuntimePrlctl($fakeBin.'/prlctl', $prlctlLog, json_encode([
            ['name' => 'base-os-ssh', 'id' => '{snapshot-1}'],
        ], JSON_THROW_ON_ERROR));
        $this->writeFakeRuntimeSsh($fakeBin.'/ssh', $sandbox.'/ssh.log');
        $this->writeFakeRuntimeScp($fakeBin.'/scp', $sandbox.'/scp.log');
        $this->writeProofPrivilegeToolchain($fakeBin, $sandbox.'/sudo.log');

        $process = $this->runHostProof(
            $tempRoot,
            $fakeBin,
            [
                '--vm-name', 'Ubuntu Server 22.04.5 LTS-test-cleanvm',
                '--snapshot-name', 'base-os-ssh',
                '--ssh-host', '192.0.2.10',
                '--ssh-user', 'ubuntu',
                '--remote-proof-root', $remoteRoot,
                '--output-dir', $outputDir,
                '--run-id', 'slice-d-pure-ref',
                '--repo-ref', $firstCommit,
            ],
            [
                'BT_FAKE_REMOTE_PATH' => $fakeBin.':/usr/bin:/bin',
                'BT_SSH_WAIT_ATTEMPTS' => '1',
                'BT_SSH_WAIT_DELAY_SECONDS' => '0',
            ],
        );

        $remoteInstaller = (string) file_get_contents($remoteRoot.'/input/guest-install-deps.sh');
        $remoteProofRunner = (string) file_get_contents($remoteRoot.'/input/guest-blue-team-proof.sh');
        $manifest = $this->readJsonFile($outputDir.'/slice-d-pure-ref/manifest.json');

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame($firstCommit, $manifest['resolved_commit_sha'] ?? null);
        $this->assertStringContainsString('# helper-version: ref-v1', $remoteInstaller);
        $this->assertStringContainsString('# helper-version: ref-v1', $remoteProofRunner);
        $this->assertStringNotContainsString('# helper-version: workspace-v2', $remoteInstaller);
        $this->assertStringNotContainsString('# helper-version: workspace-v2', $remoteProofRunner);
    }

    public function test_host_proof_passes_guest_state_dir_and_collects_obs_runtime_projection(): void
    {
        $tempRoot = $this->makeTempDir();
        $sandbox = $this->makeTempDir();
        $fakeBin = $sandbox.'/fake-bin';
        $remoteRoot = $sandbox.'/remote-proof';
        $stateDir = $remoteRoot.'/state';
        $outputDir = $sandbox.'/artifacts';
        $prlctlLog = $sandbox.'/prlctl.log';
        $sshLog = $sandbox.'/ssh.log';

        $this->installHostProofScript($tempRoot);
        $this->initializeRuntimeProofRepo($tempRoot, 'success');
        mkdir($fakeBin, 0777, true);
        mkdir($stateDir.'/runtime', 0777, true);
        mkdir($stateDir.'/rendered', 0777, true);

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

        $this->writeFakeRuntimePrlctl($fakeBin.'/prlctl', $prlctlLog, json_encode([
            ['name' => 'base-os-ssh', 'id' => '{snapshot-1}'],
        ], JSON_THROW_ON_ERROR));
        $this->writeFakeRuntimeSsh($fakeBin.'/ssh', $sshLog);
        $this->writeFakeRuntimeScp($fakeBin.'/scp', $sandbox.'/scp.log');
        $this->writeProofPrivilegeToolchain($fakeBin, $sandbox.'/sudo.log');

        $process = $this->runHostProof(
            $tempRoot,
            $fakeBin,
            [
                '--vm-name', 'Ubuntu Server 22.04.5 LTS-test-cleanvm',
                '--snapshot-name', 'base-os-ssh',
                '--ssh-host', '192.0.2.10',
                '--ssh-user', 'ubuntu',
                '--remote-proof-root', $remoteRoot,
                '--output-dir', $outputDir,
                '--run-id', 'slice-e-projection',
            ],
            [
                'BT_FAKE_REMOTE_PATH' => $fakeBin.':/usr/bin:/bin',
                'BT_SSH_WAIT_ATTEMPTS' => '1',
                'BT_SSH_WAIT_DELAY_SECONDS' => '0',
                'BT_STATE_DIR' => $stateDir,
            ],
        );

        $projectionPath = $outputDir.'/slice-e-projection/guest-output/obs-runtime-metadata.json';
        $projection = $this->readJsonFile($projectionPath);
        $projectionContents = (string) file_get_contents($projectionPath);
        $sshOutput = (string) file_get_contents($sshLog);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame('obs_runtime_metadata', $projection['record_type'] ?? null);
        $this->assertSame(2, $projection['generated_secret_audit']['record_count'] ?? null);
        $this->assertStringContainsString('BT_STATE_DIR='.$stateDir, $sshOutput);
        $this->assertStringNotContainsString('super-secret-session', $projectionContents);
        $this->assertStringNotContainsString('not-for-export', $projectionContents);
        $this->assertFileDoesNotExist($outputDir.'/slice-e-projection/guest-output/obs.generated.env');
        $this->assertFileDoesNotExist($outputDir.'/slice-e-projection/guest-output/obs.generated-secrets.jsonl');
    }

    public function test_host_proof_pinned_run_emits_proof_grade_result_and_uses_algorithm_specific_keyscan(): void
    {
        $tempRoot = $this->makeTempDir();
        $sandbox = $this->makeTempDir();
        $fakeBin = $sandbox.'/fake-bin';
        $remoteRoot = $sandbox.'/remote-proof';
        $outputDir = $sandbox.'/artifacts';
        $prlctlLog = $sandbox.'/prlctl.log';
        $sshLog = $sandbox.'/ssh.log';
        $keyscanLog = $sandbox.'/keyscan.log';

        $this->installHostProofScript($tempRoot);
        $this->initializeRuntimeProofRepo($tempRoot, 'success');
        mkdir($fakeBin, 0777, true);
        mkdir($remoteRoot, 0777, true);

        $this->writeFakeRuntimePrlctl($fakeBin.'/prlctl', $prlctlLog, json_encode([
            ['name' => 'base-os-ssh', 'id' => '{snapshot-1}'],
        ], JSON_THROW_ON_ERROR));
        $this->writeFakeRuntimeSsh($fakeBin.'/ssh', $sshLog);
        $this->writeFakeRuntimeScp($fakeBin.'/scp', $sandbox.'/scp.log');
        $this->writeProofPrivilegeToolchain($fakeBin, $sandbox.'/sudo.log');
        $this->writeFakeKeyscan($fakeBin.'/ssh-keyscan', $keyscanLog, "192.0.2.10 ssh-ed25519 AAAATESTKEY\n");
        $this->writeFakeKeygen($fakeBin.'/ssh-keygen', 'SHA256:pinnedfingerprint');

        $process = $this->runHostProof(
            $tempRoot,
            $fakeBin,
            [
                '--vm-name', 'Ubuntu Server 22.04.5 LTS-test-cleanvm',
                '--snapshot-name', 'base-os-ssh',
                '--ssh-host', '192.0.2.10',
                '--ssh-user', 'ubuntu',
                '--remote-proof-root', $remoteRoot,
                '--output-dir', $outputDir,
                '--run-id', 'slice-d-pinned',
                '--expected-host-key-algorithm', 'ed25519',
                '--expected-host-fingerprint', 'SHA256:pinnedfingerprint',
            ],
            [
                'BT_FAKE_REMOTE_PATH' => $fakeBin.':/usr/bin:/bin',
                'BT_SSH_WAIT_ATTEMPTS' => '1',
                'BT_SSH_WAIT_DELAY_SECONDS' => '0',
                'BT_HOST_KEY_FETCH_ATTEMPTS' => '1',
                'BT_HOST_KEY_FETCH_DELAY_SECONDS' => '0',
            ],
        );

        $result = $this->readJsonFile($outputDir.'/slice-d-pinned/result.json');
        $sshOutput = (string) file_get_contents($sshLog);
        $keyscanOutput = (string) file_get_contents($keyscanLog);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame('pinned', $result['ssh_identity_mode'] ?? null);
        $this->assertSame('ed25519', $result['ssh_host_key_algorithm'] ?? null);
        $this->assertSame('proof-grade', $result['assurance_level'] ?? null);
        $this->assertSame('PASS', $result['overall_status'] ?? null);
        $this->assertStringContainsString('-t ed25519', $keyscanOutput);
        $this->assertStringContainsString('StrictHostKeyChecking=yes', $sshOutput);
    }

    public function test_host_proof_pinned_run_distinguishes_key_fetch_failure_from_fingerprint_mismatch(): void
    {
        $tempRoot = $this->makeTempDir();
        $sandbox = $this->makeTempDir();
        $fakeBin = $sandbox.'/fake-bin';
        $remoteRoot = $sandbox.'/remote-proof';
        $outputDir = $sandbox.'/artifacts';

        $this->installHostProofScript($tempRoot);
        $this->initializeRuntimeProofRepo($tempRoot, 'success');
        mkdir($fakeBin, 0777, true);
        mkdir($remoteRoot, 0777, true);

        $this->writeFakeRuntimePrlctl($fakeBin.'/prlctl', $sandbox.'/prlctl.log', json_encode([
            ['name' => 'base-os-ssh', 'id' => '{snapshot-1}'],
        ], JSON_THROW_ON_ERROR));
        $this->writeFakeRuntimeSsh($fakeBin.'/ssh', $sandbox.'/ssh.log');
        $this->writeFakeRuntimeScp($fakeBin.'/scp', $sandbox.'/scp.log');
        $this->writeProofPrivilegeToolchain($fakeBin, $sandbox.'/sudo.log');
        $this->writeFakeKeyscan($fakeBin.'/ssh-keyscan', $sandbox.'/keyscan.log', '', 0);
        $this->writeFakeKeygen($fakeBin.'/ssh-keygen', 'SHA256:pinnedfingerprint');

        $fetchFailure = $this->runHostProof(
            $tempRoot,
            $fakeBin,
            [
                '--vm-name', 'Ubuntu Server 22.04.5 LTS-test-cleanvm',
                '--snapshot-name', 'base-os-ssh',
                '--ssh-host', '192.0.2.10',
                '--ssh-user', 'ubuntu',
                '--remote-proof-root', $remoteRoot,
                '--output-dir', $outputDir,
                '--run-id', 'slice-d-fetch-fail',
                '--expected-host-key-algorithm', 'ed25519',
                '--expected-host-fingerprint', 'SHA256:pinnedfingerprint',
            ],
            [
                'BT_FAKE_REMOTE_PATH' => $fakeBin.':/usr/bin:/bin',
                'BT_SSH_WAIT_ATTEMPTS' => '1',
                'BT_SSH_WAIT_DELAY_SECONDS' => '0',
                'BT_HOST_KEY_FETCH_ATTEMPTS' => '1',
                'BT_HOST_KEY_FETCH_DELAY_SECONDS' => '0',
            ],
        );

        $fetchResult = $this->readJsonFile($outputDir.'/slice-d-fetch-fail/result.json');
        $this->assertNotSame(0, $fetchFailure->getExitCode());
        $this->assertSame('host_key_fetch_failed', $fetchResult['primary_failure_code'] ?? null);

        $this->writeFakeKeyscan($fakeBin.'/ssh-keyscan', $sandbox.'/keyscan.log', "192.0.2.10 ssh-ed25519 AAAATESTKEY\n", 0);
        $this->writeFakeKeygen($fakeBin.'/ssh-keygen', 'SHA256:wrongfingerprint');

        $mismatch = $this->runHostProof(
            $tempRoot,
            $fakeBin,
            [
                '--vm-name', 'Ubuntu Server 22.04.5 LTS-test-cleanvm',
                '--snapshot-name', 'base-os-ssh',
                '--ssh-host', '192.0.2.10',
                '--ssh-user', 'ubuntu',
                '--remote-proof-root', $remoteRoot,
                '--output-dir', $outputDir,
                '--run-id', 'slice-d-mismatch',
                '--expected-host-key-algorithm', 'ed25519',
                '--expected-host-fingerprint', 'SHA256:pinnedfingerprint',
            ],
            [
                'BT_FAKE_REMOTE_PATH' => $fakeBin.':/usr/bin:/bin',
                'BT_SSH_WAIT_ATTEMPTS' => '1',
                'BT_SSH_WAIT_DELAY_SECONDS' => '0',
                'BT_HOST_KEY_FETCH_ATTEMPTS' => '1',
                'BT_HOST_KEY_FETCH_DELAY_SECONDS' => '0',
            ],
        );

        $mismatchResult = $this->readJsonFile($outputDir.'/slice-d-mismatch/result.json');
        $this->assertNotSame(0, $mismatch->getExitCode());
        $this->assertSame('host_key_fingerprint_mismatch', $mismatchResult['primary_failure_code'] ?? null);
    }

    public function test_host_proof_preserves_primary_failure_when_proof_fails_and_restore_also_fails(): void
    {
        $tempRoot = $this->makeTempDir();
        $sandbox = $this->makeTempDir();
        $fakeBin = $sandbox.'/fake-bin';
        $remoteRoot = $sandbox.'/remote-proof';
        $outputDir = $sandbox.'/artifacts';

        $this->installHostProofScript($tempRoot);
        $this->initializeRuntimeProofRepo($tempRoot, 'fail-host');
        mkdir($fakeBin, 0777, true);
        mkdir($remoteRoot, 0777, true);

        $this->writeFakeRuntimePrlctl(
            $fakeBin.'/prlctl',
            $sandbox.'/prlctl.log',
            json_encode([['name' => 'base-os-ssh', 'id' => '{snapshot-1}']], JSON_THROW_ON_ERROR),
            true
        );
        $this->writeFakeRuntimeSsh($fakeBin.'/ssh', $sandbox.'/ssh.log');
        $this->writeFakeRuntimeScp($fakeBin.'/scp', $sandbox.'/scp.log');
        $this->writeProofPrivilegeToolchain($fakeBin, $sandbox.'/sudo.log');

        $process = $this->runHostProof(
            $tempRoot,
            $fakeBin,
            [
                '--vm-name', 'Ubuntu Server 22.04.5 LTS-test-cleanvm',
                '--snapshot-name', 'base-os-ssh',
                '--ssh-host', '192.0.2.10',
                '--ssh-user', 'ubuntu',
                '--remote-proof-root', $remoteRoot,
                '--output-dir', $outputDir,
                '--run-id', 'slice-e-fail',
            ],
            [
                'BT_FAKE_REMOTE_PATH' => $fakeBin.':/usr/bin:/bin',
                'BT_SSH_WAIT_ATTEMPTS' => '1',
                'BT_SSH_WAIT_DELAY_SECONDS' => '0',
            ],
        );

        $result = $this->readJsonFile($outputDir.'/slice-e-fail/result.json');

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertSame('execution', $result['primary_failure_phase'] ?? null);
        $this->assertSame('proof_command_failed', $result['primary_failure_code'] ?? null);
        $this->assertSame('FAIL', $result['proof_status'] ?? null);
        $this->assertSame('PASS', $result['artifact_status'] ?? null);
        $this->assertSame('FAIL', $result['restore_status'] ?? null);
        $this->assertSame('FAIL', $result['overall_status'] ?? null);
        $this->assertFileExists($outputDir.'/slice-e-fail/guest-output/logs/20-bootstrap-host.log');
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-proof-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
    }

    private function installHostProofScript(string $tempRoot): void
    {
        $scriptPath = $this->repoRoot.'/ops/proof/pd-cleanvm-proof.sh';
        $this->assertFileExists($scriptPath, 'Expected host proof orchestrator script to exist.');
        $this->writeExecutable(
            $tempRoot.'/ops/proof/pd-cleanvm-proof.sh',
            (string) file_get_contents($scriptPath),
        );
        $this->writeExecutable(
            $tempRoot.'/ops/proof/guest-install-deps.sh',
            (string) file_get_contents($this->repoRoot.'/ops/proof/guest-install-deps.sh'),
        );
        $this->writeExecutable(
            $tempRoot.'/ops/proof/guest-blue-team-proof.sh',
            (string) file_get_contents($this->repoRoot.'/ops/proof/guest-blue-team-proof.sh'),
        );
    }

    private function initializeGitRepo(string $tempRoot): string
    {
        file_put_contents($tempRoot.'/tracked.txt', "baseline\n");
        $this->mustRun(['git', 'init'], $tempRoot);
        $this->mustRun(['git', 'config', 'user.name', 'Test User'], $tempRoot);
        $this->mustRun(['git', 'config', 'user.email', 'tests@example.com'], $tempRoot);
        $this->mustRun(['git', 'add', '.'], $tempRoot);
        $this->mustRun(['git', 'commit', '-m', 'Initial commit'], $tempRoot);

        return trim($this->mustRun(['git', 'rev-parse', 'HEAD'], $tempRoot)->getOutput());
    }

    private function initializeRuntimeProofRepo(string $tempRoot, string $setupMode): string
    {
        mkdir($tempRoot.'/ops/smoke', 0777, true);
        file_put_contents($tempRoot.'/tracked.txt', "baseline\n");

        $setupScript = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'setup:%s\n' "${1:-}" >> "${BT_PROOF_SEQUENCE_LOG}"
BASH;

        if ($setupMode === 'fail-host') {
            $setupScript .= "\nif [[ \"\${1:-}\" == \"host\" ]]; then\n  exit 9\nfi\n";
        }

        $this->writeExecutable($tempRoot.'/setup-blue-team-vm.sh', $setupScript);
        $this->writeExecutable($tempRoot.'/ops/smoke/run-all.sh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'smoke\n' >> "${BT_PROOF_SEQUENCE_LOG}"
BASH);

        $this->mustRun(['git', 'init'], $tempRoot);
        $this->mustRun(['git', 'config', 'user.name', 'Test User'], $tempRoot);
        $this->mustRun(['git', 'config', 'user.email', 'tests@example.com'], $tempRoot);
        $this->mustRun(['git', 'add', '.'], $tempRoot);
        $this->mustRun(['git', 'commit', '-m', 'Initial proof repo'], $tempRoot);

        return trim($this->mustRun(['git', 'rev-parse', 'HEAD'], $tempRoot)->getOutput());
    }

    private function writeFakePrlctl(string $fakeBin, string $logPath, string $snapshotJson): void
    {
        $this->writeExecutable($fakeBin.'/prlctl', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$logPath}"
if [[ "\${1:-}" == "snapshot-list" && "\${3:-}" == "--json" ]]; then
  cat <<'JSON'
{$snapshotJson}
JSON
  exit 0
fi
exit 0
BASH);
    }

    private function writeFakeRuntimePrlctl(string $path, string $logPath, string $snapshotJson, bool $failSecondSnapshotSwitch = false): void
    {
        $stateFile = dirname($path).'/prlctl-state';
        $failSecondSwitch = $failSecondSnapshotSwitch ? '1' : '0';

        $this->writeExecutable($path, <<<BASH
#!/usr/bin/env bash
set -euo pipefail
state_file="{$stateFile}"
printf '%s\n' "\$*" >> "{$logPath}"
case "\${1:-}" in
  snapshot-list)
    cat <<'JSON'
{$snapshotJson}
JSON
    exit 0
    ;;
  snapshot-switch)
    count=0
    if [[ -f "\${state_file}" ]]; then
      count="\$(cat "\${state_file}")"
    fi
    count="\$((count + 1))"
    printf '%s' "\${count}" > "\${state_file}"
    if [[ "{$failSecondSwitch}" == "1" && "\${count}" -ge 2 ]]; then
      exit 1
    fi
    exit 0
    ;;
  start|stop)
    exit 0
    ;;
esac
exit 0
BASH);
    }

    private function writeFakeRuntimeSsh(string $path, string $logPath): void
    {
        $this->writeExecutable($path, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> LOG_PATH_PLACEHOLDER
command_string="${@: -1}"
if [[ "${command_string}" == "__bt_ping__" ]]; then
  exit 0
fi
PATH="${BT_FAKE_REMOTE_PATH:-/usr/bin:/bin}" bash -c "${command_string}"
BASH);

        $contents = (string) file_get_contents($path);
        $contents = str_replace('LOG_PATH_PLACEHOLDER', str_replace('\\', '\\\\', $logPath), $contents);
        file_put_contents($path, $contents);
        chmod($path, 0755);
    }

    private function writeFakeRuntimeScp(string $path, string $logPath): void
    {
        $this->writeExecutable($path, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> LOG_PATH_PLACEHOLDER
recursive=0
args=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    -r)
      recursive=1
      shift
      ;;
    -P)
      shift 2
      ;;
    -o)
      shift 2
      ;;
    *)
      args+=("$1")
      shift
      ;;
  esac
done
src="${args[0]}"
dest="${args[1]}"
copy_path() {
  local source="$1"
  local target="$2"
  mkdir -p "$(dirname "${target}")"
  if [[ -d "${source}" || "${recursive}" == "1" ]]; then
    rm -rf "${target}"
    cp -R "${source}" "${target}"
  else
    cp "${source}" "${target}"
  fi
}
if [[ "${src}" == *:* ]]; then
  copy_path "${src#*:}" "${dest}"
else
  copy_path "${src}" "${dest#*:}"
fi
BASH);

        $contents = (string) file_get_contents($path);
        $contents = str_replace('LOG_PATH_PLACEHOLDER', str_replace('\\', '\\\\', $logPath), $contents);
        file_put_contents($path, $contents);
        chmod($path, 0755);
    }

    private function writeFakeKeyscan(string $path, string $logPath, string $output, int $exitCode = 0): void
    {
        $this->writeExecutable($path, <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$logPath}"
cat <<'EOF'
{$output}
EOF
exit {$exitCode}
BASH);
    }

    private function writeFakeKeygen(string $path, string $fingerprint): void
    {
        $this->writeExecutable($path, <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '256 %s comment (ED25519)\n' "{$fingerprint}"
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

        $this->writeExecutable($binDir.'/sha256sum', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
shasum -a 256 "${1:-}"
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

    /**
     * @param array<int, string> $args
     */
    private function runHostProof(string $tempRoot, string $fakeBin, array $args, array $extraEnv = []): Process
    {
        $process = new Process(
            array_merge([$tempRoot.'/ops/proof/pd-cleanvm-proof.sh'], $args),
            $tempRoot,
            array_merge(['PATH' => $fakeBin.':'.getenv('PATH')], $extraEnv),
            null,
            20,
        );
        $process->run();

        return $process;
    }

    /**
     * @param array<int, string> $command
     */
    private function mustRun(array $command, string $cwd): Process
    {
        $process = new Process($command, $cwd, null, null, 20);
        $process->mustRun();

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
    private function readJsonFile(string $path): array
    {
        $this->assertFileExists($path);
        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
