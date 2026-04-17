<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Verification path: sqlite-safe.
 */
class GuestInstallDepsShellContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_guest_install_skips_apt_when_required_toolchain_is_already_present(): void
    {
        $tempRoot = $this->makeTempDir();
        $fakeBin = $tempRoot.'/fake-bin';
        $aptLog = $tempRoot.'/apt.log';
        $sudoLog = $tempRoot.'/sudo.log';

        mkdir($fakeBin, 0777, true);
        $this->installGuestScript($tempRoot);
        $this->writeStubToolchain($fakeBin);
        $this->writeExecutable($fakeBin.'/sudo', "#!/usr/bin/env bash\nset -euo pipefail\nprintf '%s\\n' \"\$*\" >> \"{$sudoLog}\"\nexit 99\n");
        $this->writeExecutable($fakeBin.'/apt-get', "#!/usr/bin/env bash\nset -euo pipefail\nprintf '%s\\n' \"\$*\" >> \"{$aptLog}\"\nexit 98\n");

        $logFile = $tempRoot.'/proof-output/logs/01-guest-install-deps.log';
        $process = $this->runGuestInstaller($tempRoot, $fakeBin, $logFile);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('Guest dependency toolchain already present.', $process->getOutput().$process->getErrorOutput());
        $this->assertFileExists($logFile);
        $this->assertSame('0644', substr(sprintf('%o', fileperms($logFile)), -4));
        $this->assertSame('', file_exists($sudoLog) ? (string) file_get_contents($sudoLog) : '');
        $this->assertSame('', file_exists($aptLog) ? (string) file_get_contents($aptLog) : '');
    }

    public function test_guest_install_uses_noninteractive_sudo_apt_chain_and_verifies_sha256sum(): void
    {
        $tempRoot = $this->makeTempDir();
        $fakeBin = $tempRoot.'/fake-bin';
        $aptLog = $tempRoot.'/apt.log';
        $sudoLog = $tempRoot.'/sudo.log';
        $stateRoot = $tempRoot.'/state';
        $installBin = $stateRoot.'/installed-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($installBin, 0777, true);
        $this->installGuestScript($tempRoot);
        $this->writeExecutable($fakeBin.'/id', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "-un" ]]; then
  printf 'proof-user\n'
  exit 0
fi
exit 94
BASH);
        $this->writeExecutable($fakeBin.'/sudo', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$sudoLog}"
if [[ "\${1:-}" != "-n" ]]; then
  exit 91
fi
shift
"\$@"
BASH);
        $this->writeExecutable($fakeBin.'/groupadd', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");
        $this->writeExecutable($fakeBin.'/usermod', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");
        $this->writeExecutable($fakeBin.'/apt-get', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf 'DEBIAN_FRONTEND=%s CMD=%s\n' "\${DEBIAN_FRONTEND:-}" "\$*" >> "{$aptLog}"
if [[ "\${1:-}" == "update" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "install" ]]; then
  cat > "{$installBin}/docker" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
if [[ "\${1:-}" == "--version" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "compose" && "\${2:-}" == "version" ]]; then
  exit 0
fi
exit 0
EOF
  cat > "{$installBin}/git" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
exit 0
EOF
  cat > "{$installBin}/curl" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
exit 0
EOF
  cat > "{$installBin}/jq" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
exit 0
EOF
  cat > "{$installBin}/python3" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
exit 0
EOF
  cat > "{$installBin}/sha256sum" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
exit 0
EOF
  chmod 0755 "{$installBin}/docker" "{$installBin}/git" "{$installBin}/curl" "{$installBin}/jq" "{$installBin}/python3" "{$installBin}/sha256sum"
  exit 0
fi
exit 92
BASH);

        $logFile = $tempRoot.'/proof-output/logs/01-guest-install-deps.log';
        $process = $this->runGuestInstaller($tempRoot, $installBin.':'.$fakeBin, $logFile);
        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $aptOutput = (string) @file_get_contents($aptLog);
        $sudoOutput = (string) @file_get_contents($sudoLog);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('Installing guest proof dependencies.', $combinedOutput);
        $this->assertStringContainsString('Guest dependency toolchain ready.', $combinedOutput);
        $this->assertStringContainsString('DEBIAN_FRONTEND=noninteractive CMD=update', $aptOutput);
        $this->assertStringContainsString('DEBIAN_FRONTEND=noninteractive CMD=install -y ca-certificates coreutils curl docker.io docker-compose-plugin git jq python3', $aptOutput);
        $this->assertStringContainsString('-n apt-get update', $sudoOutput);
        $this->assertStringContainsString('-n apt-get install -y ca-certificates coreutils curl docker.io docker-compose-plugin git jq python3', $sudoOutput);
        $this->assertStringContainsString('-n groupadd -f docker', $sudoOutput);
        $this->assertStringContainsString('-n usermod -aG docker proof-user', $sudoOutput);
        $this->assertFileExists($logFile);
    }

    public function test_guest_install_fails_loud_when_required_toolchain_is_still_missing_after_install(): void
    {
        $tempRoot = $this->makeTempDir();
        $fakeBin = $tempRoot.'/fake-bin';
        $aptLog = $tempRoot.'/apt.log';

        mkdir($fakeBin, 0777, true);
        $this->installGuestScript($tempRoot);
        $this->writeExecutable($fakeBin.'/id', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "-un" ]]; then
  printf 'proof-user\n'
  exit 0
fi
exit 94
BASH);
        $this->writeExecutable($fakeBin.'/sudo', "#!/usr/bin/env bash\nset -euo pipefail\nif [[ \"\${1:-}\" != \"-n\" ]]; then exit 91; fi\nshift\n\"\$@\"\n");
        $this->writeExecutable($fakeBin.'/groupadd', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");
        $this->writeExecutable($fakeBin.'/usermod', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");
        $this->writeExecutable($fakeBin.'/apt-get', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf 'DEBIAN_FRONTEND=%s CMD=%s\n' "\${DEBIAN_FRONTEND:-}" "\$*" >> "{$aptLog}"
exit 0
BASH);

        $logFile = $tempRoot.'/proof-output/logs/01-guest-install-deps.log';
        $process = $this->runGuestInstaller($tempRoot, $fakeBin, $logFile);
        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('Installing guest proof dependencies.', $combinedOutput);
        $this->assertStringContainsString('Required tool check failed: docker', $combinedOutput);
        $this->assertStringContainsString('Guest dependency toolchain verification failed after install.', $combinedOutput);
        $this->assertStringContainsString('DEBIAN_FRONTEND=noninteractive CMD=update', (string) @file_get_contents($aptLog));
        $this->assertFileExists($logFile);
    }

    public function test_run_privileged_propagates_noninteractive_sudo_failures_in_shell_contracts(): void
    {
        $tempRoot = $this->makeTempDir();
        $fakeBin = $tempRoot.'/fake-bin';
        $sudoLog = $tempRoot.'/sudo.log';

        mkdir($fakeBin, 0777, true);
        $scriptPath = $this->installGuestScriptForSourceContract($tempRoot);
        $sudoStub = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "__SUDO_LOG__"
if [[ "${1:-}" != "-n" ]]; then
  exit 91
fi
exit 42
BASH;
        $this->writeExecutable($fakeBin.'/sudo', str_replace('__SUDO_LOG__', $sudoLog, $sudoStub));

        $process = new Process(
            ['bash', '-lc', 'source "$0"; if run_privileged false; then exit 0; else status=$?; exit "$status"; fi;', $scriptPath],
            $tempRoot,
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'BT_TEST_SKIP_MAIN' => '1',
                'HOME' => $tempRoot,
            ],
            null,
            20,
        );
        $process->run();

        $runningAsRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;

        $this->assertSame($runningAsRoot ? 1 : 42, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame($runningAsRoot ? '' : "-n false\n", file_exists($sudoLog) ? (string) file_get_contents($sudoLog) : '');
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-guest-install-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
    }

    private function installGuestScript(string $tempRoot): void
    {
        $scriptPath = $this->repoRoot.'/ops/proof/guest-install-deps.sh';
        $this->assertFileExists($scriptPath, 'Expected guest dependency installer script to exist.');
        $this->writeExecutable(
            $tempRoot.'/ops/proof/guest-install-deps.sh',
            (string) file_get_contents($scriptPath),
        );
    }

    private function installGuestScriptForSourceContract(string $tempRoot): string
    {
        $scriptPath = $this->repoRoot.'/ops/proof/guest-install-deps.sh';
        $this->assertFileExists($scriptPath, 'Expected guest dependency installer script to exist.');

        $contents = (string) file_get_contents($scriptPath);
        $contents = str_replace(
            "main \"\$@\"\n",
            "if [[ \"\${BT_TEST_SKIP_MAIN:-0}\" != \"1\" ]]; then\n    main \"\$@\"\nfi\n",
            $contents,
        );

        $targetPath = $tempRoot.'/ops/proof/guest-install-deps.sh';
        $this->writeExecutable($targetPath, $contents);

        return $targetPath;
    }

    private function writeStubToolchain(string $binDir): void
    {
        $this->writeExecutable($binDir.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "--version" ]]; then
  exit 0
fi
if [[ "${1:-}" == "compose" && "${2:-}" == "version" ]]; then
  exit 0
fi
exit 0
BASH);

        foreach (['git', 'curl', 'jq', 'python3', 'sha256sum'] as $tool) {
            $this->writeExecutable($binDir.'/'.$tool, "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");
        }
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

    private function runGuestInstaller(string $tempRoot, string $path, string $logFile): Process
    {
        $process = new Process(
            [$tempRoot.'/ops/proof/guest-install-deps.sh'],
            $tempRoot,
            [
                'PATH' => $path.':'.getenv('PATH'),
                'BT_PROOF_OUTPUT_DIR' => dirname(dirname($logFile)),
                'HOME' => $tempRoot,
            ],
            null,
            20,
        );
        $process->run();

        return $process;
    }
}
