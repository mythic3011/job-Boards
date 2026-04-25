<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class AppEnvCompatibilityValidationTest extends TestCase
{
    private string $repoRoot;
    private string $validatorPath;
    private string $mappingPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
        $this->validatorPath = $this->repoRoot.'/ops/bootstrap/validate-app-env-map.py';
        $this->mappingPath = $this->repoRoot.'/ops/bootstrap/app-env-map.json';
    }

    public function test_validator_projects_compatibility_aliases_and_generated_paths_from_canonical_values(): void
    {
        $stateDir = $this->makeTempDir().'/state';
        $process = $this->runValidator(
            $this->mappingPath,
            [
                'APP_DOMAIN' => 'jobs-board.lab',
                'STATE_DIR' => $stateDir,
            ],
        );

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        $resolved = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('jobs-board.lab', $resolved['APP_DOMAIN'] ?? null);
        $this->assertSame('https://jobs-board.lab', $resolved['APP_URL'] ?? null);
        $this->assertSame($stateDir, $resolved['STATE_DIR'] ?? null);
        $this->assertSame($stateDir, $resolved['BT_STATE_DIR'] ?? null);
        $this->assertSame($stateDir.'/runtime', $resolved['BT_RUNTIME_DIR'] ?? null);
        $this->assertSame($stateDir.'/runtime/grafana-admin-secret', $resolved['GRAFANA_ADMIN_SECRET_FILE'] ?? null);
        $this->assertSame($stateDir.'/rendered/grafana.datasources.yml', $resolved['GRAFANA_DATASOURCES_FILE'] ?? null);
        $this->assertSame($stateDir.'/rendered/prometheus.web-config.yml', $resolved['PROMETHEUS_WEB_CONFIG_FILE'] ?? null);
    }

    public function test_validator_fails_when_compatibility_aliases_conflict_with_canonical_values(): void
    {
        $stateDir = $this->makeTempDir().'/state';
        $process = $this->runValidator(
            $this->mappingPath,
            [
                'APP_DOMAIN' => 'jobs-board.lab',
                'APP_URL' => 'https://other.example.test',
                'STATE_DIR' => $stateDir,
                'BT_STATE_DIR' => $stateDir.'-other',
            ],
        );

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('APP_URL', $process->getErrorOutput());
        $this->assertStringContainsString('BT_STATE_DIR', $process->getErrorOutput());
    }

    public function test_validator_rejects_host_shell_env_as_canonical_ownership_proof(): void
    {
        $process = $this->runValidator(
            $this->mappingPath,
            [
                'APP_URL' => 'https://jobs-board.lab',
            ],
            [
                'APP_DOMAIN' => 'jobs-board.lab',
            ],
        );

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('APP_DOMAIN', $process->getErrorOutput());
        $this->assertStringContainsString('explicit values', $process->getErrorOutput());
    }

    public function test_validator_requires_the_task_two_canonical_roots(): void
    {
        $mapping = $this->loadMapping();
        unset($mapping['mappings']['APP_DOMAIN']);

        $process = $this->runValidator($this->writeJsonFixture('mapping.json', $mapping));

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('APP_DOMAIN', $process->getErrorOutput());
        $this->assertStringContainsString('required canonical', strtolower($process->getErrorOutput()));
    }

    public function test_validator_requires_consumer_and_owner_proof_for_non_removable_entries(): void
    {
        $mapping = $this->loadMapping();
        $mapping['mappings']['APP_URL']['ownerProof'] = ' ';

        $process = $this->runValidator($this->writeJsonFixture('mapping.json', $mapping));

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('APP_URL', $process->getErrorOutput());
        $this->assertStringContainsString('ownerProof', $process->getErrorOutput());
    }

    private function runValidator(string $mappingPath, ?array $values = null, array $environment = []): Process
    {
        $command = ['python3', $this->validatorPath, $mappingPath];

        if ($values !== null) {
            $command[] = $this->writeJsonFixture('values.json', $values);
        }

        $process = new Process($command, $this->repoRoot, $environment, null, 20);
        $process->run();

        return $process;
    }

    /**
     * @return array{
     *     version: string,
     *     mappings: array<string, array<string, mixed>>
     * }
     */
    private function loadMapping(): array
    {
        return json_decode((string) file_get_contents($this->mappingPath), true, 512, JSON_THROW_ON_ERROR);
    }

    private function writeJsonFixture(string $filename, array $payload): string
    {
        $dir = $this->makeTempDir();
        $path = $dir.'/'.$filename;
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $path;
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-app-env-map-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
    }
}
