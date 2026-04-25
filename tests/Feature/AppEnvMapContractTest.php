<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AppEnvMapContractTest extends TestCase
{
    private const REQUIRED_TASK_ONE_ENTRY_NAMES = [
        'APP_DOMAIN',
        'APP_URL',
        'SSL_CERT_DOMAIN',
        'STATE_DIR',
        'BT_STATE_DIR',
        'BT_RUNTIME_DIR',
        'GRAFANA_ADMIN_SECRET_FILE',
        'GRAFANA_DATASOURCES_FILE',
        'PROMETHEUS_WEB_CONFIG_FILE',
    ];

    private const REQUIRED_TASK_ONE_CANONICAL_ROOTS = [
        'APP_DOMAIN',
        'STATE_DIR',
    ];

    private const ALLOWED_CLASSIFICATIONS = [
        'canonical',
        'compatibility-alias',
        'generated-internal',
    ];

    private const ALLOWED_OWNERSHIP = [
        'operator',
        'bootstrap',
    ];

    private const ALLOWED_LIFECYCLE = [
        'defaulted',
        'derived',
    ];

    private const ALLOWED_TEMPLATE_ACTIONS = [
        'keep-normal',
        'advanced-doc',
        'compatibility-only',
        'remove-normal',
    ];

    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_mapping_file_exists_and_each_entry_has_the_required_contract_fields(): void
    {
        $mapping = $this->loadMapping();

        $this->assertSame('1.0.0', $mapping['version'] ?? null);
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

            $this->assertContains(
                $entry['classification'],
                self::ALLOWED_CLASSIFICATIONS,
                sprintf('Invalid classification on "%s".', $mappedKey),
            );
            $this->assertContains(
                $entry['ownership'],
                self::ALLOWED_OWNERSHIP,
                sprintf('Invalid ownership on "%s".', $mappedKey),
            );
            $this->assertContains(
                $entry['lifecycle'],
                self::ALLOWED_LIFECYCLE,
                sprintf('Invalid lifecycle on "%s".', $mappedKey),
            );
            $this->assertContains(
                $entry['templateAction'],
                self::ALLOWED_TEMPLATE_ACTIONS,
                sprintf('Invalid templateAction on "%s".', $mappedKey),
            );
        }
    }

    public function test_mapping_contains_the_required_task_one_entry_set_even_if_later_tasks_expand_it(): void
    {
        $mapping = $this->loadMapping();
        $mappedKeys = array_keys($mapping['mappings']);

        foreach (self::REQUIRED_TASK_ONE_ENTRY_NAMES as $entryName) {
            $this->assertContains(
                $entryName,
                $mappedKeys,
                sprintf('Expected Task 1 minimum entry "%s" to remain declared even if later tasks expand the map.', $entryName),
            );
        }
    }

    public function test_mapping_canonical_references_resolve_and_include_the_task_one_core_roots(): void
    {
        $mapping = $this->loadMapping();
        $canonicalEntries = [];
        $requiredCanonicalNames = $mapping['requiredCanonicalNames'] ?? null;

        $this->assertIsArray($requiredCanonicalNames);

        foreach ($mapping['mappings'] as $entryName => $entry) {
            if ($entry['classification'] === 'canonical') {
                $canonicalEntries[$entryName] = true;
                $this->assertSame(
                    $entryName,
                    $entry['canonicalName'],
                    sprintf('Canonical entry "%s" must point canonicalName to itself.', $entryName),
                );
            }
        }

        foreach (self::REQUIRED_TASK_ONE_CANONICAL_ROOTS as $rootName) {
            $this->assertArrayHasKey(
                $rootName,
                $canonicalEntries,
                sprintf('Expected Task 1 canonical root "%s" to remain canonical.', $rootName),
            );
            $this->assertContains(
                $rootName,
                $requiredCanonicalNames,
                sprintf('Expected requiredCanonicalNames to retain the Task 1 canonical root "%s".', $rootName),
            );
        }

        foreach ($mapping['mappings'] as $entryName => $entry) {
            $this->assertArrayHasKey(
                $entry['canonicalName'],
                $canonicalEntries,
                sprintf('Entry "%s" references non-canonical "%s".', $entryName, $entry['canonicalName']),
            );
        }
    }

    public function test_mapping_covers_the_exact_required_canonical_and_generated_entries_for_task_one(): void
    {
        $mapping = $this->loadMapping();
        $entries = $mapping['mappings'];

        $this->assertSame('APP_DOMAIN', $entries['APP_DOMAIN']['canonicalName'] ?? null);
        $this->assertSame('STATE_DIR', $entries['STATE_DIR']['canonicalName'] ?? null);
        $this->assertSame('STATE_DIR', $entries['BT_RUNTIME_DIR']['canonicalName'] ?? null);
        $this->assertSame('STATE_DIR', $entries['GRAFANA_ADMIN_SECRET_FILE']['canonicalName'] ?? null);
        $this->assertSame('STATE_DIR', $entries['GRAFANA_DATASOURCES_FILE']['canonicalName'] ?? null);
        $this->assertSame('STATE_DIR', $entries['PROMETHEUS_WEB_CONFIG_FILE']['canonicalName'] ?? null);
    }

    /**
     * @return array{
     *     version: string,
     *     mappings: array<string, array<string, string>>
     * }
     */
    private function loadMapping(): array
    {
        $mappingPath = $this->repoRoot.'/ops/bootstrap/app-env-map.json';

        $this->assertFileExists($mappingPath);

        $decoded = json_decode((string) file_get_contents($mappingPath), true);

        $this->assertIsArray($decoded, 'Expected app-env-map.json to decode to an object.');
        $this->assertArrayHasKey('version', $decoded);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', (string) $decoded['version']);

        return $decoded;
    }
}
