<?php

namespace App\Services;

use RuntimeException;

class CanonicalAuditContract
{
    /**
     * @var array<string, mixed>
     */
    private array $definition;

    public function __construct(?string $path = null)
    {
        $contractPath = $path ?? base_path('config/contracts/canonical-audit.v1.json');

        $raw = @file_get_contents($contractPath);
        if ($raw === false) {
            throw new RuntimeException("Unable to read canonical audit contract at [{$contractPath}].");
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Canonical audit contract must decode to an object.');
        }

        foreach (['version', 'payload', 'dedupe', 'metadata', 'time_fields', 'normalized_enums', 'events'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $decoded)) {
                throw new RuntimeException("Canonical audit contract is missing required key [{$requiredKey}].");
            }
        }

        $this->definition = $decoded;
    }

    public function version(): string
    {
        return (string) $this->definition['version'];
    }

    /**
     * @return array<int, string>
     */
    public function dedupeIdentityFields(): array
    {
        /** @var array<int, string> $identityFields */
        $identityFields = $this->definition['dedupe']['identity_fields'] ?? [];

        return $identityFields;
    }

    public function isAdmissibleEvent(string $eventType): bool
    {
        return (bool) ($this->definition['events'][$eventType]['admissible'] ?? false);
    }

    public function metadataKeyLimit(): int
    {
        return (int) ($this->definition['metadata']['max_keys'] ?? 0);
    }

    public function metadataValueLengthLimit(): int
    {
        return (int) ($this->definition['metadata']['max_value_length'] ?? 0);
    }

    /**
     * @return array<int, string>
     */
    public function allowedMetadataKeys(): array
    {
        /** @var array<int, string> $allowedKeys */
        $allowedKeys = $this->definition['metadata']['allowed_keys'] ?? [];

        return $allowedKeys;
    }

    /**
     * @return array<int, string>
     */
    public function requiredPayloadFields(): array
    {
        /** @var array<int, string> $requiredFields */
        $requiredFields = $this->definition['payload']['required_fields'] ?? [];

        return $requiredFields;
    }

    /**
     * @return array<int, string>
     */
    public function allowedPayloadFields(): array
    {
        /** @var array<int, string> $requiredFields */
        $requiredFields = $this->definition['payload']['required_fields'] ?? [];
        /** @var array<int, string> $optionalFields */
        $optionalFields = $this->definition['payload']['optional_fields'] ?? [];

        return array_values(array_merge($requiredFields, $optionalFields));
    }

    /**
     * @return array<int, string>
     */
    public function normalizedEnum(string $field): array
    {
        /** @var array<int, string> $values */
        $values = $this->definition['normalized_enums'][$field] ?? [];

        return $values;
    }

    public function eventOutcome(string $eventType): ?string
    {
        $outcome = $this->definition['events'][$eventType]['outcome'] ?? null;

        return is_string($outcome) ? $outcome : null;
    }

    public function timeFieldMeaning(string $field): ?string
    {
        $meaning = $this->definition['time_fields'][$field]['meaning'] ?? null;

        return is_string($meaning) ? $meaning : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->definition;
    }
}
