<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

final class InstallPr2aShellContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_new_lab_command_runs_prepare_only_and_stops_with_guidance(): void
    {
        $tempRoot = $this->installFixture();
        $scriptPath = $tempRoot.'/install.sh';
        $bootstrapLog = $tempRoot.'/bootstrap.log';

        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$bootstrapLog}"
exit 0
BASH);

        $process = new Process([$scriptPath, 'lab'], $tempRoot, null, null, 20);
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $bootstrapOutput = (string) file_get_contents($bootstrapLog);

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('prepare lab', $bootstrapOutput);
        $this->assertStringContainsString('PR2B/PR3', $combinedOutput);
    }

    public function test_no_arg_non_tty_fails_fast_with_usage_guidance(): void
    {
        $tempRoot = $this->installFixture();
        $scriptPath = $tempRoot.'/install.sh';

        $input = new InputStream();
        $input->close();

        $process = new Process([$scriptPath], $tempRoot, null, $input, 5);
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('./install.sh lab', $combinedOutput);
        $this->assertStringContainsString('non-interactive', strtolower($combinedOutput));
    }

    public function test_reset_demo_is_validate_only_stop_in_pr2a(): void
    {
        $tempRoot = $this->installFixture();
        $scriptPath = $tempRoot.'/install.sh';
        $bootstrapLog = $tempRoot.'/bootstrap.log';

        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$bootstrapLog}"
exit 0
BASH);

        $process = new Process([$scriptPath, 'reset-demo'], $tempRoot, null, null, 20);
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $bootstrapOutput = (string) file_get_contents($bootstrapLog);

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('prepare reset-demo', $bootstrapOutput);
        $this->assertStringContainsString('validate-only-stop', $combinedOutput);
    }

    public function test_unknown_legacy_combination_fails_with_guidance(): void
    {
        $tempRoot = $this->installFixture();
        $scriptPath = $tempRoot.'/install.sh';

        $process = new Process([$scriptPath, 'demo', 'production'], $tempRoot, null, null, 20);
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('migration', strtolower($combinedOutput));
    }

    private function installFixture(): string
    {
        $tempRoot = sys_get_temp_dir().'/jobs-boards-install-pr2a-'.bin2hex(random_bytes(6));
        mkdir($tempRoot, 0777, true);

        copy($this->repoRoot.'/install.sh', $tempRoot.'/install.sh');
        chmod($tempRoot.'/install.sh', 0755);

        mkdir($tempRoot.'/ops/lib', 0777, true);
        copy($this->repoRoot.'/ops/lib/common.sh', $tempRoot.'/ops/lib/common.sh');
        copy($this->repoRoot.'/ops/lib/config-contract.sh', $tempRoot.'/ops/lib/config-contract.sh');

        mkdir($tempRoot.'/ops/bootstrap', 0777, true);
        copy($this->repoRoot.'/ops/bootstrap/contract.json', $tempRoot.'/ops/bootstrap/contract.json');
        copy($this->repoRoot.'/ops/bootstrap/validate-contract.py', $tempRoot.'/ops/bootstrap/validate-contract.py');

        mkdir($tempRoot.'/ops/bin', 0777, true);
        $resolverSource = $this->repoRoot.'/ops/bin/resolve-config-contract';
        if (is_file($resolverSource)) {
            copy($resolverSource, $tempRoot.'/ops/bin/resolve-config-contract');
            chmod($tempRoot.'/ops/bin/resolve-config-contract', 0755);
        }

        file_put_contents($tempRoot.'/.env', "APP_NAME=Jobs Boards\n");
        file_put_contents($tempRoot.'/.env.example', "APP_NAME=Jobs Boards\n");

        return $tempRoot;
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
