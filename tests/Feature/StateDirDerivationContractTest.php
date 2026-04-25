<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\ObsConfigContract;

final class StateDirDerivationContractTest extends TestCase
{
    private string $repoRoot;
    private string $resolverPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
        $this->resolverPath = $this->repoRoot.'/ops/bin/resolve-config-contract';
    }

    public function test_generated_path_keys_derive_from_canonical_state_dir(): void
    {
        $stateDir = $this->makeTempDir().'/canonical-state';
        $resolved = $this->resolve([
            'STATE_DIR' => $stateDir,
        ]);

        $this->assertSame($stateDir, $resolved['STATE_DIR'] ?? null);
        $this->assertSame($stateDir, $resolved['BT_STATE_DIR'] ?? null);
        $this->assertSame(ObsConfigContract::derivedPath($stateDir, 'PROMETHEUS_WEB_CONFIG_FILE'), $resolved['PROMETHEUS_WEB_CONFIG_FILE'] ?? null);
        $this->assertSame(ObsConfigContract::derivedPath($stateDir, 'GRAFANA_DATASOURCES_FILE'), $resolved['GRAFANA_DATASOURCES_FILE'] ?? null);
        $this->assertSame(ObsConfigContract::derivedPath($stateDir, 'GRAFANA_ADMIN_SECRET_FILE'), $resolved['GRAFANA_ADMIN_SECRET_FILE'] ?? null);
    }

    public function test_relative_state_dir_override_resolves_against_root_dir(): void
    {
        $rootDir = $this->makeTempDir();
        $resolved = $this->resolve([
            'ROOT_DIR' => $rootDir,
            'STATE_DIR' => 'ops-state/relative',
        ]);

        $expectedStateDir = $rootDir.'/ops-state/relative';

        $this->assertSame($expectedStateDir, $resolved['STATE_DIR'] ?? null);
        $this->assertSame($expectedStateDir, $resolved['BT_STATE_DIR'] ?? null);
        $this->assertSame(ObsConfigContract::derivedPath($expectedStateDir, 'PROMETHEUS_WEB_CONFIG_FILE'), $resolved['PROMETHEUS_WEB_CONFIG_FILE'] ?? null);
    }

    public function test_bt_state_dir_alias_still_resolves_to_same_final_paths(): void
    {
        $stateDir = $this->makeTempDir().'/legacy-alias-state';
        $resolved = $this->resolve([
            'BT_STATE_DIR' => $stateDir,
        ]);

        $this->assertSame($stateDir, $resolved['STATE_DIR'] ?? null);
        $this->assertSame($stateDir, $resolved['BT_STATE_DIR'] ?? null);
        $this->assertSame(ObsConfigContract::derivedPath($stateDir, 'PROMETHEUS_WEB_CONFIG_FILE'), $resolved['PROMETHEUS_WEB_CONFIG_FILE'] ?? null);
        $this->assertSame(ObsConfigContract::derivedPath($stateDir, 'GRAFANA_DATASOURCES_FILE'), $resolved['GRAFANA_DATASOURCES_FILE'] ?? null);
        $this->assertSame(ObsConfigContract::derivedPath($stateDir, 'GRAFANA_ADMIN_SECRET_FILE'), $resolved['GRAFANA_ADMIN_SECRET_FILE'] ?? null);
    }

    public function test_bt_state_dir_relative_override_resolves_against_root_dir_when_state_dir_is_not_set(): void
    {
        $rootDir = $this->makeTempDir();
        $resolved = $this->resolve([
            'ROOT_DIR' => $rootDir,
            'BT_STATE_DIR' => 'legacy/relative-state',
        ]);

        $expectedStateDir = $rootDir.'/legacy/relative-state';

        $this->assertSame($expectedStateDir, $resolved['STATE_DIR'] ?? null);
        $this->assertSame($expectedStateDir, $resolved['BT_STATE_DIR'] ?? null);
        $this->assertSame(ObsConfigContract::derivedPath($expectedStateDir, 'PROMETHEUS_WEB_CONFIG_FILE'), $resolved['PROMETHEUS_WEB_CONFIG_FILE'] ?? null);
    }

    public function test_state_dir_and_bt_state_dir_equivalent_paths_do_not_fail_conflict_check(): void
    {
        $rootDir = $this->makeTempDir();
        $absoluteStateDir = $rootDir.'/shared/state';
        $resolved = $this->resolve([
            'ROOT_DIR' => $rootDir,
            'STATE_DIR' => 'shared/state',
            'BT_STATE_DIR' => $absoluteStateDir,
        ]);

        $this->assertSame($absoluteStateDir, $resolved['STATE_DIR'] ?? null);
        $this->assertSame($absoluteStateDir, $resolved['BT_STATE_DIR'] ?? null);
    }

    public function test_state_dir_and_bt_state_dir_conflict_fails_fast(): void
    {
        $rootDir = $this->makeTempDir();
        $process = $this->runResolver([
            'ROOT_DIR' => $rootDir,
            'STATE_DIR' => 'state-a',
            'BT_STATE_DIR' => 'state-b',
        ]);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('STATE_DIR and BT_STATE_DIR', $process->getErrorOutput());
        $this->assertStringContainsString('different paths', $process->getErrorOutput());
    }

    public function test_generated_path_consumers_do_not_require_operator_to_set_path_overrides(): void
    {
        $resolved = $this->resolve([
            'STATE_DIR' => $this->makeTempDir().'/state',
        ]);

        $this->assertArrayHasKey('PROMETHEUS_WEB_CONFIG_FILE', $resolved);
        $this->assertArrayHasKey('GRAFANA_DATASOURCES_FILE', $resolved);
        $this->assertArrayHasKey('GRAFANA_ADMIN_SECRET_FILE', $resolved);
        $this->assertStringNotContainsString('${', $resolved['PROMETHEUS_WEB_CONFIG_FILE']);
        $this->assertStringNotContainsString('${', $resolved['GRAFANA_DATASOURCES_FILE']);
        $this->assertStringNotContainsString('${', $resolved['GRAFANA_ADMIN_SECRET_FILE']);
    }

    /**
     * @param array<string, string|false> $environment
     * @return array<string, string>
     */
    private function resolve(array $environment): array
    {
        $process = $this->runResolver($environment);
        $process->mustRun();

        /** @var array<string, string> $decoded */
        $decoded = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string, string|false> $environment
     */
    private function runResolver(array $environment): Process
    {
        $baseEnvironment = [
            'ROOT_DIR' => false,
            'STATE_DIR' => false,
            'BT_STATE_DIR' => false,
            'PROMETHEUS_WEB_CONFIG_FILE' => false,
            'GRAFANA_DATASOURCES_FILE' => false,
            'GRAFANA_ADMIN_SECRET_FILE' => false,
        ];
        $processEnvironment = array_merge($baseEnvironment, $environment);
        $process = new Process(['python3', $this->resolverPath, 'json'], $this->repoRoot, $processEnvironment, null, 20);
        $process->run();

        return $process;
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-state-dir-derivation-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
    }
}
