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

        copy($this->repoRoot.'/install.sh', $scriptPath);
        chmod($scriptPath, 0755);

        file_put_contents($tempRoot.'/.env', "APP_PORT=8080\nAPP_SSL_PORT=8443\n");
        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', "#!/usr/bin/env bash\nexit 0\n");

        $this->writeExecutable($fakeBin.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "compose" && "${2:-}" == "version" ]]; then
  exit 0
fi
if [[ "${1:-}" == "exec" ]]; then
  exit 1
fi
if [[ "${1:-}" == "compose" && "${2:-}" == "build" ]]; then
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
