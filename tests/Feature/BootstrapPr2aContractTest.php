<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class BootstrapPr2aContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_contract_validator_accepts_repo_contract_json(): void
    {
        $process = new Process(
            ['python3', $this->repoRoot.'/ops/bootstrap/validate-contract.py', $this->repoRoot.'/ops/bootstrap/contract.json'],
            $this->repoRoot,
            null,
            null,
            20,
        );
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
    }

    public function test_contract_validator_fails_when_required_sections_are_missing(): void
    {
        $tempRoot = $this->makeTempDir();
        $brokenContract = $tempRoot.'/contract.json';
        file_put_contents($brokenContract, json_encode([
            'modeDefaults' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $process = new Process(
            ['python3', $this->repoRoot.'/ops/bootstrap/validate-contract.py', $brokenContract],
            $this->repoRoot,
            null,
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('protectedCanonicalValues', $combinedOutput);
        $this->assertStringContainsString('resetDemoBehavior', $combinedOutput);
    }

    public function test_prepare_lab_writes_shell_compatible_outputs_under_default_state_dir(): void
    {
        $tempRoot = $this->makeTempDir('jobs boards contract ');
        $scriptPath = $this->bootstrapFixture($tempRoot);

        file_put_contents($tempRoot.'/.env.example', "APP_NAME=Jobs Boards\n");
        file_put_contents($tempRoot.'/.env', "APP_NAME=Jobs Boards\n");

        $process = new Process(
            [$scriptPath, 'prepare', 'lab'],
            $tempRoot,
            [
                'APP_DOMAIN' => 'jobs-board.lab',
                'STATE_DIR' => $tempRoot.'/state dir/with$dollar',
                'BT_BOOTSTRAP_INJECTED_KEYS' => 'APP_DOMAIN,STATE_DIR',
            ],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $compatFile = $tempRoot.'/state dir/with$dollar/runtime/compat.shell.env';
        $resolvedTempRoot = (string) realpath($tempRoot);

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertFileExists($compatFile);

        $contents = file_get_contents($compatFile);
        $this->assertIsString($contents);
        $this->assertStringContainsString("STATE_DIR='".$resolvedTempRoot."/state dir/with\$dollar'", $contents);
        $this->assertStringContainsString("APP_DOMAIN='jobs-board.lab'", $contents);
        $this->assertStringNotContainsString('${', $contents);
    }

    public function test_prepare_rejects_newline_bearing_values_for_shell_output(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->bootstrapFixture($tempRoot);

        file_put_contents($tempRoot.'/.env.example', "APP_NAME=Jobs Boards\n");
        file_put_contents($tempRoot.'/.env', "APP_NAME=Jobs Boards\n");

        $process = new Process(
            [$scriptPath, 'prepare', 'lab'],
            $tempRoot,
            [
                'APP_DOMAIN' => "jobs-board.lab\nbad",
                'BT_BOOTSTRAP_INJECTED_KEYS' => 'APP_DOMAIN',
            ],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertNotSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringContainsString('newline', strtolower($combinedOutput));
    }

    public function test_prepare_production_does_not_modify_existing_app_key_or_previous_keys(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->bootstrapFixture($tempRoot);

        file_put_contents($tempRoot.'/.env.example', "APP_KEY=\nAPP_PREVIOUS_KEYS=\n");
        file_put_contents($tempRoot.'/.env', "APP_KEY=base64:current-key\nAPP_PREVIOUS_KEYS=base64:old-one,base64:old-two\nMONITORING_PASSWORD=monitoring-secret\nSESSION_SECRET=session-secret\nDB_PASSWORD=db-secret\n");

        $process = new Process(
            [$scriptPath, 'prepare', 'production'],
            $tempRoot,
            [
                'APP_DOMAIN' => 'jobs-board.example.com',
                'BT_BOOTSTRAP_INJECTED_KEYS' => 'APP_DOMAIN',
            ],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $envContents = file_get_contents($tempRoot.'/.env');

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertIsString($envContents);
        $this->assertStringContainsString('APP_KEY=base64:current-key', $envContents);
        $this->assertStringContainsString('APP_PREVIOUS_KEYS=base64:old-one,base64:old-two', $envContents);
    }

    private function bootstrapFixture(string $tempRoot): string
    {
        $scriptPath = $tempRoot.'/bootstrap-env.sh';

        copy($this->repoRoot.'/bootstrap-env.sh', $scriptPath);
        chmod($scriptPath, 0755);

        mkdir($tempRoot.'/ops/bootstrap', 0777, true);
        copy($this->repoRoot.'/ops/bootstrap/contract.json', $tempRoot.'/ops/bootstrap/contract.json');
        copy($this->repoRoot.'/ops/bootstrap/validate-contract.py', $tempRoot.'/ops/bootstrap/validate-contract.py');

        mkdir($tempRoot.'/ops/lib', 0777, true);
        copy($this->repoRoot.'/ops/lib/common.sh', $tempRoot.'/ops/lib/common.sh');
        copy($this->repoRoot.'/ops/lib/config-contract.sh', $tempRoot.'/ops/lib/config-contract.sh');

        mkdir($tempRoot.'/ops/bin', 0777, true);
        $resolverSource = $this->repoRoot.'/ops/bin/resolve-config-contract';
        if (is_file($resolverSource)) {
            copy($resolverSource, $tempRoot.'/ops/bin/resolve-config-contract');
            chmod($tempRoot.'/ops/bin/resolve-config-contract', 0755);
        }

        return $scriptPath;
    }

    private function makeTempDir(string $prefix = 'jobs-boards-pr2a-'): string
    {
        $dir = sys_get_temp_dir().'/'.$prefix.bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        return $dir;
    }
}
