<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\ObsConfigContract;

class ConfigContractResolverTest extends TestCase
{
    private string $repoRoot;
    private string $resolverPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
        $this->resolverPath = $this->repoRoot.'/ops/bin/resolve-config-contract';
    }

    public function test_manifest_file_is_a_yml_path_with_strict_json_syntax_only(): void
    {
        $contents = file_get_contents(ObsConfigContract::manifestPath());

        $this->assertIsString($contents);
        $this->assertJson($contents);
        $this->assertStringNotContainsString('#', $contents);
        $this->assertStringContainsString('"defaults"', $contents);
        $this->assertStringContainsString('"derived"', $contents);
    }

    public function test_resolver_reports_strict_json_parse_errors_for_yaml_style_manifest_text(): void
    {
        $tempDir = $this->makeTempDir();
        $manifestPath = $tempDir.'/config-contract.yml';
        file_put_contents($manifestPath, "defaults:\n  BT_STATE_DIR: .blue-team-vm\n");

        $process = new Process(
            ['python3', $this->resolverPath, 'json'],
            $this->repoRoot,
            ['BT_CONFIG_CONTRACT_MANIFEST' => $manifestPath],
            null,
            20,
        );
        $process->run();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('.yml path', $process->getErrorOutput());
        $this->assertStringContainsString('strict JSON required', $process->getErrorOutput());
        $this->assertStringContainsString('YAML comments/unquoted keys invalid', $process->getErrorOutput());
    }

    public function test_resolver_defaults_bt_state_dir_and_derives_all_obs_paths_together(): void
    {
        $process = new Process(['python3', $this->resolverPath, 'json'], $this->repoRoot, null, null, 20);
        $process->mustRun();

        $resolved = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(ObsConfigContract::DEFAULT_STATE_DIR, $resolved['BT_STATE_DIR']);
        $this->assertSame(
            ObsConfigContract::derivedPath(ObsConfigContract::DEFAULT_STATE_DIR, 'PROMETHEUS_WEB_CONFIG_FILE'),
            $resolved['PROMETHEUS_WEB_CONFIG_FILE'],
        );
        $this->assertSame(
            ObsConfigContract::derivedPath(ObsConfigContract::DEFAULT_STATE_DIR, 'GRAFANA_DATASOURCES_FILE'),
            $resolved['GRAFANA_DATASOURCES_FILE'],
        );
        $this->assertSame(
            ObsConfigContract::derivedPath(ObsConfigContract::DEFAULT_STATE_DIR, 'GRAFANA_ADMIN_SECRET_FILE'),
            $resolved['GRAFANA_ADMIN_SECRET_FILE'],
        );
    }

    public function test_resolver_preserves_explicit_env_override_for_resolver_consumers_only(): void
    {
        $tempDir = $this->makeTempDir();
        $stateDir = $tempDir.'/state';
        $explicitSecretPath = $tempDir."/override/graph ana's secret";

        $process = new Process(
            ['sh', '-c', 'eval "$("$1" shell)"; printf "%s\n%s\n%s\n%s" "$BT_STATE_DIR" "$PROMETHEUS_WEB_CONFIG_FILE" "$GRAFANA_DATASOURCES_FILE" "$GRAFANA_ADMIN_SECRET_FILE"', 'resolver-test', $this->resolverPath],
            $this->repoRoot,
            [
                'BT_STATE_DIR' => $stateDir,
                'GRAFANA_ADMIN_SECRET_FILE' => $explicitSecretPath,
            ],
            null,
            20,
        );
        $process->mustRun();

        $lines = preg_split('/\R/', trim($process->getOutput()));

        $this->assertSame($stateDir, $lines[0] ?? null);
        $this->assertSame(ObsConfigContract::derivedPath($stateDir, 'PROMETHEUS_WEB_CONFIG_FILE'), $lines[1] ?? null);
        $this->assertSame(ObsConfigContract::derivedPath($stateDir, 'GRAFANA_DATASOURCES_FILE'), $lines[2] ?? null);
        $this->assertSame($explicitSecretPath, $lines[3] ?? null);
    }

    public function test_resolver_fails_fast_on_unknown_keys(): void
    {
        $process = new Process(['python3', $this->resolverPath, 'key', 'NOT_A_REAL_KEY'], $this->repoRoot, null, null, 20);
        $process->run();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('Unknown config contract key', $process->getErrorOutput());
    }

    public function test_shell_resolver_consumers_do_not_duplicate_phase1_default_obs_path_literals(): void
    {
        $shellConsumers = [
            $this->repoRoot.'/bootstrap-env.sh',
            $this->repoRoot.'/ops/bootstrap/bootstrap-obs.sh',
            $this->repoRoot.'/ops/lib/config-contract.sh',
        ];

        foreach ($shellConsumers as $path) {
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $this->assertStringNotContainsString(
                ObsConfigContract::derivedPath(ObsConfigContract::DEFAULT_STATE_DIR, 'PROMETHEUS_WEB_CONFIG_FILE'),
                $contents,
                $path,
            );
            $this->assertStringNotContainsString(
                ObsConfigContract::derivedPath(ObsConfigContract::DEFAULT_STATE_DIR, 'GRAFANA_DATASOURCES_FILE'),
                $contents,
                $path,
            );
            $this->assertStringNotContainsString(
                ObsConfigContract::derivedPath(ObsConfigContract::DEFAULT_STATE_DIR, 'GRAFANA_ADMIN_SECRET_FILE'),
                $contents,
                $path,
            );
        }
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-config-contract-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
    }
}
