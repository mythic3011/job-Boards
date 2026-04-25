<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class InstallDemoModeContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_demo_mode_is_non_destructive_and_uses_runtime_bridge_apply(): void
    {
        $tempRoot = $this->installFixture();
        $scriptPath = $tempRoot.'/install.sh';
        $bootstrapLog = $tempRoot.'/bootstrap.log';
        $appLog = $tempRoot.'/bootstrap-app.log';
        $obsLog = $tempRoot.'/bootstrap-obs.log';
        $dockerLog = $tempRoot.'/docker.log';

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

        $this->writeExecutable($tempRoot.'/fake-bin/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
exit 0
BASH);

        $process = new Process(
            [$scriptPath, 'demo'],
            $tempRoot,
            ['PATH' => $tempRoot.'/fake-bin:'.getenv('PATH')],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $dockerOutput = is_file($dockerLog) ? (string) file_get_contents($dockerLog) : '';

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('prepare demo', (string) file_get_contents($bootstrapLog));
        $this->assertStringContainsString('apply', (string) file_get_contents($appLog));
        $this->assertStringContainsString('apply', (string) file_get_contents($obsLog));
        $this->assertSame('', $dockerOutput);
        $this->assertDoesNotMatchRegularExpression('/migrate:fresh|db:wipe|schema:drop/i', $dockerOutput);
        $this->assertStringNotContainsString('reset-demo wipes the local database', $combinedOutput);
        $this->assertStringContainsString('PR3 runtime bridge apply completed for mode: demo', $combinedOutput);
    }

    public function test_reset_demo_is_the_explicit_destructive_path(): void
    {
        $tempRoot = $this->installFixture();
        $scriptPath = $tempRoot.'/install.sh';
        $bootstrapLog = $tempRoot.'/bootstrap.log';
        $dockerLog = $tempRoot.'/docker.log';

        $this->writeExecutable($tempRoot.'/bootstrap-env.sh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$bootstrapLog}"
exit 0
BASH);

        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-nginx-ssl.sh', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");
        $this->writeExecutable($tempRoot.'/ops/bootstrap/bootstrap-obs.sh', "#!/usr/bin/env bash\nset -euo pipefail\nexit 0\n");

        $this->writeExecutable($tempRoot.'/fake-bin/docker', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$dockerLog}"
exit 0
BASH);

        $process = new Process(
            [$scriptPath, 'reset-demo'],
            $tempRoot,
            [
                'PATH' => $tempRoot.'/fake-bin:'.getenv('PATH'),
                'INSTALL_ASSUME_YES' => 'true',
                'INSTALL_ADMIN_EMAIL' => 'admin@example.com',
                'INSTALL_ADMIN_NICKNAME' => 'Admin',
                'INSTALL_ADMIN_PASSWORD' => 'very-strong-pass-123',
            ],
            null,
            25,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $bootstrapOutput = (string) file_get_contents($bootstrapLog);
        $dockerOutput = (string) file_get_contents($dockerLog);

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('dev', $bootstrapOutput);
        $this->assertStringNotContainsString('prepare reset-demo', $bootstrapOutput);
        $this->assertStringContainsString('php artisan migrate:fresh --force', $dockerOutput);
        $this->assertStringContainsString('php artisan install:headless', $dockerOutput);
    }

    private function installFixture(): string
    {
        $tempRoot = sys_get_temp_dir().'/jobs-boards-install-demo-'.bin2hex(random_bytes(6));
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

        mkdir($tempRoot.'/fake-bin', 0777, true);
        file_put_contents($tempRoot.'/.env', "APP_NAME=Jobs Boards\n");
        file_put_contents($tempRoot.'/.env.example', "APP_NAME=Jobs Boards\n");
        file_put_contents($tempRoot.'/compose.yaml', "services:\n  laravel.test: {}\n");
        file_put_contents($tempRoot.'/compose.app.yml', "services:\n  laravel.test: {}\n");
        file_put_contents($tempRoot.'/compose.obs.yml', "services:\n  grafana: {}\n");

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
