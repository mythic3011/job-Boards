<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class InstallPr3RuntimeShellContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_lab_mode_runs_prepare_then_split_runtime_apply(): void
    {
        $tempRoot = $this->installFixture();
        $scriptPath = $tempRoot.'/install.sh';
        $bootstrapLog = $tempRoot.'/bootstrap.log';
        $appLog = $tempRoot.'/bootstrap-app.log';
        $obsLog = $tempRoot.'/bootstrap-obs.log';

        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$bootstrapLog}"
exit 0
BASH);

        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-app.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf 'action=%s\n' "\${1:-}" >> "{$appLog}"
printf 'compose_app=%s\n' "\${BT_COMPOSE_APP_FILE:-}" >> "{$appLog}"
exit 0
BASH);

        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf 'action=%s\n' "\${1:-}" >> "{$obsLog}"
printf 'compose_obs=%s\n' "\${BT_COMPOSE_OBS_FILE:-}" >> "{$obsLog}"
exit 0
BASH);

        $process = new Process([$scriptPath, 'lab'], $tempRoot, null, null, 20);
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $bootstrapOutput = (string) file_get_contents($bootstrapLog);
        $appOutput = (string) file_get_contents($appLog);
        $obsOutput = (string) file_get_contents($obsLog);

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('prepare lab', $bootstrapOutput);
        $this->assertStringContainsString('action=apply', $appOutput);
        $this->assertStringContainsString('compose_app='.$tempRoot.'/compose.app.yml', $appOutput);
        $this->assertStringContainsString('action=apply', $obsOutput);
        $this->assertStringContainsString('compose_obs='.$tempRoot.'/compose.obs.yml', $obsOutput);
        $this->assertStringNotContainsString('PR2B/PR3', $combinedOutput);
        $this->assertStringContainsString('PR3 runtime bridge apply completed for mode: lab', $combinedOutput);
    }

    public function test_production_mode_runs_bridge_apply_without_prepare_only_stop_message(): void
    {
        $tempRoot = $this->installFixture();
        $scriptPath = $tempRoot.'/install.sh';
        $bootstrapLog = $tempRoot.'/bootstrap.log';
        $appLog = $tempRoot.'/bootstrap-app.log';
        $obsLog = $tempRoot.'/bootstrap-obs.log';

        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$bootstrapLog}"
exit 0
BASH);

        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-app.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$appLog}"
exit 0
BASH);

        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$obsLog}"
exit 0
BASH);

        $process = new Process([$scriptPath, 'production'], $tempRoot, null, null, 20);
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $bootstrapOutput = (string) file_get_contents($bootstrapLog);

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('prepare production', $bootstrapOutput);
        $this->assertStringContainsString('apply', (string) file_get_contents($appLog));
        $this->assertStringContainsString('apply', (string) file_get_contents($obsLog));
        $this->assertStringNotContainsString('prepare-only', strtolower($combinedOutput));
        $this->assertStringContainsString('PR3 runtime bridge apply completed for mode: production', $combinedOutput);
    }

    public function test_demo_production_legacy_combination_is_blocked_with_guidance(): void
    {
        $tempRoot = $this->installFixture();
        $scriptPath = $tempRoot.'/install.sh';
        $process = new Process([$scriptPath, 'demo', 'production'], $tempRoot, null, null, 20);
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString(
            'Migration required: legacy destructive demo semantics are blocked.',
            $combinedOutput
        );
    }

    public function test_lab_with_extra_argument_fails_with_generic_guidance(): void
    {
        $tempRoot = $this->installFixture();
        $scriptPath = $tempRoot.'/install.sh';
        $process = new Process([$scriptPath, 'lab', 'production'], $tempRoot, null, null, 20);
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString("Unsupported extra argument for mode 'lab': production", $combinedOutput);
    }

    private function installFixture(): string
    {
        $tempRoot = sys_get_temp_dir().'/jobs-boards-install-pr3-'.bin2hex(random_bytes(6));
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
