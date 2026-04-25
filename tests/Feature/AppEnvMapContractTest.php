<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AppEnvMapContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_mapping_file_exists_and_each_entry_has_the_required_contract_fields(): void
    {
        $mapping = $this->loadMapping();

        $this->assertArrayHasKey('mappings', $mapping);
        $this->assertIsArray($mapping['mappings']);
        $this->assertNotEmpty($mapping['mappings']);

        $requiredFields = [
            'name',
            'semanticRole',
            'canonicalName',
            'classification',
            'ownership',
            'lifecycle',
            'templateAction',
            'consumerProof',
            'ownerProof',
        ];

        foreach ($mapping['mappings'] as $mappedKey => $entry) {
            $this->assertIsArray($entry, sprintf('Expected "%s" mapping entry to be an object.', $mappedKey));
            $this->assertSame($mappedKey, $entry['name'] ?? null, sprintf('Expected "%s" mapping name to match its key.', $mappedKey));

            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $entry, sprintf('Missing "%s" on "%s".', $field, $mappedKey));
                $this->assertIsString($entry[$field], sprintf('Expected "%s" on "%s" to be a string.', $field, $mappedKey));
                $this->assertNotSame('', trim($entry[$field]), sprintf('Expected "%s" on "%s" to be non-empty.', $field, $mappedKey));
            }
        }
    }

    public function test_mapping_covers_canonical_domain_state_and_generated_runtime_concepts(): void
    {
        $mapping = $this->loadMapping();
        $canonicalNames = array_map(
            static fn (array $entry): string => $entry['canonicalName'],
            array_values($mapping['mappings']),
        );

        $this->assertContains('APP_DOMAIN', $canonicalNames);
        $this->assertContains('STATE_DIR', $canonicalNames);
        $this->assertNotEmpty(array_intersect(
            [
                'BT_RUNTIME_DIR',
                'GRAFANA_ADMIN_SECRET_FILE',
                'GRAFANA_DATASOURCES_FILE',
                'PROMETHEUS_WEB_CONFIG_FILE',
            ],
            $canonicalNames,
        ));
    }

    /**
     * @return array{
     *     mappings: array<string, array<string, string>>
     * }
     */
    private function loadMapping(): array
    {
        $mappingPath = $this->repoRoot.'/ops/bootstrap/app-env-map.json';

        $this->assertFileExists($mappingPath);

        $decoded = json_decode((string) file_get_contents($mappingPath), true);

        $this->assertIsArray($decoded, 'Expected app-env-map.json to decode to an object.');

        return $decoded;
    }
}
