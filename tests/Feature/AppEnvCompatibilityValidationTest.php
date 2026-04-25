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

    public function test_validator_projects_aliases_and_generated_paths_for_added_metadata_without_name_specific_logic(): void
    {
        $mapping = $this->loadMapping();
        $mapping['requiredCanonicalNames'][] = 'CACHE_ROOT';
        $mapping['mappings']['CACHE_ROOT'] = [
            'name' => 'CACHE_ROOT',
            'semanticRole' => 'cache-root',
            'canonicalName' => 'CACHE_ROOT',
            'classification' => 'canonical',
            'ownership' => 'bootstrap',
            'lifecycle' => 'defaulted',
            'templateAction' => 'advanced-doc',
            'valueDerivation' => 'identity',
            'conflictPolicy' => 'canonical-source-of-truth',
            'consumerProof' => 'Cache root is canonical for generated cache runtime paths.',
            'ownerProof' => 'Bootstrap owns the default cache root in the normal flow.',
        ];
        $mapping['mappings']['LEGACY_CACHE_ROOT'] = [
            'name' => 'LEGACY_CACHE_ROOT',
            'semanticRole' => 'cache-root',
            'canonicalName' => 'CACHE_ROOT',
            'classification' => 'compatibility-alias',
            'ownership' => 'bootstrap',
            'lifecycle' => 'derived',
            'templateAction' => 'compatibility-only',
            'valueDerivation' => 'identity',
            'conflictPolicy' => 'must-match-derived-canonical',
            'consumerProof' => 'Legacy cache consumers still read the old root variable during migration.',
            'ownerProof' => 'The legacy cache root remains a projection of the canonical cache root.',
        ];
        $mapping['mappings']['CACHE_TMP_DIR'] = [
            'name' => 'CACHE_TMP_DIR',
            'semanticRole' => 'cache-runtime-dir',
            'canonicalName' => 'CACHE_ROOT',
            'classification' => 'generated-internal',
            'ownership' => 'bootstrap',
            'lifecycle' => 'derived',
            'templateAction' => 'remove-normal',
            'valueDerivation' => 'path-join:tmp',
            'conflictPolicy' => 'must-match-derived-canonical',
            'consumerProof' => 'Cache runtime consumers use a generated tmp directory beneath the canonical cache root.',
            'ownerProof' => 'Operators do not supply cache tmp paths in the normal flow.',
        ];

        $cacheRoot = $this->makeTempDir().'/cache-root';
        $process = $this->runValidator(
            $this->writeJsonFixture('mapping.json', $mapping),
            [
                'APP_DOMAIN' => 'jobs-board.lab',
                'STATE_DIR' => $this->makeTempDir().'/state',
                'CACHE_ROOT' => $cacheRoot,
            ],
        );

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        $resolved = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($cacheRoot, $resolved['CACHE_ROOT'] ?? null);
        $this->assertSame($cacheRoot, $resolved['LEGACY_CACHE_ROOT'] ?? null);
        $this->assertSame($cacheRoot.'/tmp', $resolved['CACHE_TMP_DIR'] ?? null);
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

    public function test_validator_rejects_unknown_explicit_env_keys(): void
    {
        $process = $this->runValidator(
            $this->mappingPath,
            [
                'APP_DOMAIN' => 'jobs-board.lab',
                'STATE_DIR' => $this->makeTempDir().'/state',
                'NOT_DECLARED_IN_MAP' => 'unexpected',
            ],
        );

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('NOT_DECLARED_IN_MAP', $process->getErrorOutput());
        $this->assertStringContainsString('not declared', strtolower($process->getErrorOutput()));
    }

    public function test_validator_requires_all_declared_required_canonical_entries_to_exist(): void
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

    public function test_validator_requires_entry_name_to_match_mapping_key(): void
    {
        $mapping = $this->loadMapping();
        $mapping['mappings']['APP_URL']['name'] = 'WRONG_NAME';

        $process = $this->runValidator($this->writeJsonFixture('mapping.json', $mapping));

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('APP_URL', $process->getErrorOutput());
        $this->assertStringContainsString('name', $process->getErrorOutput());
    }

    public function test_validator_rejects_invalid_enum_membership_in_mapping_entries(): void
    {
        $cases = [
            'classification' => 'not-a-classification',
            'ownership' => 'not-an-owner',
            'lifecycle' => 'not-a-lifecycle',
            'templateAction' => 'not-a-template-action',
        ];

        foreach ($cases as $field => $invalidValue) {
            $mapping = $this->loadMapping();
            $mapping['mappings']['APP_URL'][$field] = $invalidValue;

            $process = $this->runValidator($this->writeJsonFixture('mapping.json', $mapping));

            $this->assertNotSame(0, $process->getExitCode(), sprintf('Expected %s=%s to fail validation.', $field, $invalidValue));
            $this->assertStringContainsString('APP_URL', $process->getErrorOutput());
            $this->assertStringContainsString($field, $process->getErrorOutput());
        }
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
