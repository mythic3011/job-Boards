<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class EnvExampleConsumerProofTest extends TestCase
{
    private const REMOVABLE_PLACEHOLDERS = [
        'BROADCAST_CONNECTION',
        'VITE_APP_NAME',
        'MEMCACHED_HOST',
    ];

    private const ADVANCED_ONLY_ENTRIES = [
        'AWS_ACCESS_KEY_ID',
        'AWS_SECRET_ACCESS_KEY',
        'AWS_DEFAULT_REGION',
        'AWS_BUCKET',
        'AWS_USE_PATH_STYLE_ENDPOINT',
    ];

    /**
     * @return array<string, array{
     *     configFile: string,
     *     configPath: string,
     *     value: string,
     *     expected: mixed
     * }>
     */
    private static function consumerProofCases(): array
    {
        return [
            'APP_NAME' => [
                'configFile' => 'config/app.php',
                'configPath' => 'name',
                'value' => 'Jobs Boards Proof',
                'expected' => 'Jobs Boards Proof',
            ],
            'APP_ENV' => [
                'configFile' => 'config/app.php',
                'configPath' => 'env',
                'value' => 'staging-proof',
                'expected' => 'staging-proof',
            ],
            'APP_KEY' => [
                'configFile' => 'config/app.php',
                'configPath' => 'key',
                'value' => 'base64:'.str_repeat('a', 44),
                'expected' => 'base64:'.str_repeat('a', 44),
            ],
            'APP_DEBUG' => [
                'configFile' => 'config/app.php',
                'configPath' => 'debug',
                'value' => 'false',
                'expected' => false,
            ],
            'APP_URL' => [
                'configFile' => 'config/app.php',
                'configPath' => 'url',
                'value' => 'https://proof.jobs-board.test',
                'expected' => 'https://proof.jobs-board.test',
            ],
            'APP_LOCALE' => [
                'configFile' => 'config/app.php',
                'configPath' => 'locale',
                'value' => 'zh_HK',
                'expected' => 'zh_HK',
            ],
            'APP_FALLBACK_LOCALE' => [
                'configFile' => 'config/app.php',
                'configPath' => 'fallback_locale',
                'value' => 'en',
                'expected' => 'en',
            ],
            'APP_FAKER_LOCALE' => [
                'configFile' => 'config/app.php',
                'configPath' => 'faker_locale',
                'value' => 'en_GB',
                'expected' => 'en_GB',
            ],
            'APP_MAINTENANCE_DRIVER' => [
                'configFile' => 'config/app.php',
                'configPath' => 'maintenance.driver',
                'value' => 'cache',
                'expected' => 'cache',
            ],
            'TRUSTED_PROXIES' => [
                'configFile' => 'config/app.php',
                'configPath' => 'trusted_proxies',
                'value' => '10.0.0.10,10.0.0.11',
                'expected' => ['10.0.0.10', '10.0.0.11'],
            ],
            'TRUSTED_PROXY_HEADERS' => [
                'configFile' => 'config/app.php',
                'configPath' => 'trusted_proxy_headers',
                'value' => 'forwarded',
                'expected' => 'forwarded',
            ],
            'LOG_CHANNEL' => [
                'configFile' => 'config/logging.php',
                'configPath' => 'default',
                'value' => 'stderr',
                'expected' => 'stderr',
            ],
            'LOG_STACK' => [
                'configFile' => 'config/logging.php',
                'configPath' => 'channels.stack.channels',
                'value' => 'single,daily',
                'expected' => ['single', 'daily'],
            ],
            'LOG_DEPRECATIONS_CHANNEL' => [
                'configFile' => 'config/logging.php',
                'configPath' => 'deprecations.channel',
                'value' => 'stack',
                'expected' => 'stack',
            ],
            'LOG_LEVEL' => [
                'configFile' => 'config/logging.php',
                'configPath' => 'channels.single.level',
                'value' => 'warning',
                'expected' => 'warning',
            ],
            'HTTP_LOGGING_ENABLED' => [
                'configFile' => 'config/http_logging.php',
                'configPath' => 'enabled',
                'value' => 'false',
                'expected' => false,
            ],
            'HTTP_LOGGING_SUCCESS' => [
                'configFile' => 'config/http_logging.php',
                'configPath' => 'log_success',
                'value' => 'false',
                'expected' => false,
            ],
            'HTTP_LOGGING_REDIRECTS' => [
                'configFile' => 'config/http_logging.php',
                'configPath' => 'log_redirects',
                'value' => 'false',
                'expected' => false,
            ],
            'HTTP_LOGGING_SLOW_THRESHOLD' => [
                'configFile' => 'config/http_logging.php',
                'configPath' => 'slow_request_threshold',
                'value' => '2500',
                'expected' => '2500',
            ],
            'DB_CONNECTION' => [
                'configFile' => 'config/database.php',
                'configPath' => 'default',
                'value' => 'pgsql',
                'expected' => 'pgsql',
            ],
            'DB_HOST' => [
                'configFile' => 'config/database.php',
                'configPath' => 'connections.pgsql.host',
                'value' => 'postgres-proof',
                'expected' => 'postgres-proof',
            ],
            'DB_PORT' => [
                'configFile' => 'config/database.php',
                'configPath' => 'connections.pgsql.port',
                'value' => '6543',
                'expected' => '6543',
            ],
            'DB_DATABASE' => [
                'configFile' => 'config/database.php',
                'configPath' => 'connections.pgsql.database',
                'value' => 'jobs_boards',
                'expected' => 'jobs_boards',
            ],
            'DB_USERNAME' => [
                'configFile' => 'config/database.php',
                'configPath' => 'connections.pgsql.username',
                'value' => 'jobs_user',
                'expected' => 'jobs_user',
            ],
            'DB_PASSWORD' => [
                'configFile' => 'config/database.php',
                'configPath' => 'connections.pgsql.password',
                'value' => 'secret-proof-password',
                'expected' => 'secret-proof-password',
            ],
            'REDIS_CLIENT' => [
                'configFile' => 'config/database.php',
                'configPath' => 'redis.client',
                'value' => 'predis',
                'expected' => 'predis',
            ],
            'REDIS_HOST' => [
                'configFile' => 'config/database.php',
                'configPath' => 'redis.default.host',
                'value' => 'redis-proof',
                'expected' => 'redis-proof',
            ],
            'REDIS_PASSWORD' => [
                'configFile' => 'config/database.php',
                'configPath' => 'redis.default.password',
                'value' => 'redis-secret',
                'expected' => 'redis-secret',
            ],
            'REDIS_PORT' => [
                'configFile' => 'config/database.php',
                'configPath' => 'redis.default.port',
                'value' => '6380',
                'expected' => '6380',
            ],
            'SESSION_DRIVER' => [
                'configFile' => 'config/session.php',
                'configPath' => 'driver',
                'value' => 'redis',
                'expected' => 'redis',
            ],
            'SESSION_LIFETIME' => [
                'configFile' => 'config/session.php',
                'configPath' => 'lifetime',
                'value' => '45',
                'expected' => 45,
            ],
            'SESSION_ENCRYPT' => [
                'configFile' => 'config/session.php',
                'configPath' => 'encrypt',
                'value' => 'true',
                'expected' => true,
            ],
            'SESSION_PATH' => [
                'configFile' => 'config/session.php',
                'configPath' => 'path',
                'value' => '/proof',
                'expected' => '/proof',
            ],
            'SESSION_DOMAIN' => [
                'configFile' => 'config/session.php',
                'configPath' => 'domain',
                'value' => '.jobs-board.test',
                'expected' => '.jobs-board.test',
            ],
            'SESSION_SECURE_COOKIE' => [
                'configFile' => 'config/session.php',
                'configPath' => 'secure',
                'value' => 'true',
                'expected' => true,
            ],
            'FILESYSTEM_DISK' => [
                'configFile' => 'config/filesystems.php',
                'configPath' => 'default',
                'value' => 's3',
                'expected' => 's3',
            ],
            'CACHE_STORE' => [
                'configFile' => 'config/cache.php',
                'configPath' => 'default',
                'value' => 'redis',
                'expected' => 'redis',
            ],
            'MEMCACHED_HOST' => [
                'configFile' => 'config/cache.php',
                'configPath' => 'stores.memcached.servers.0.host',
                'value' => 'memcached-proof',
                'expected' => 'memcached-proof',
            ],
            'QUEUE_CONNECTION' => [
                'configFile' => 'config/queue.php',
                'configPath' => 'default',
                'value' => 'redis',
                'expected' => 'redis',
            ],
            'MAIL_MAILER' => [
                'configFile' => 'config/mail.php',
                'configPath' => 'default',
                'value' => 'smtp',
                'expected' => 'smtp',
            ],
            'MAIL_FROM_ADDRESS' => [
                'configFile' => 'config/mail.php',
                'configPath' => 'from.address',
                'value' => 'proof@example.test',
                'expected' => 'proof@example.test',
            ],
            'MAIL_FROM_NAME' => [
                'configFile' => 'config/mail.php',
                'configPath' => 'from.name',
                'value' => 'Jobs Boards Mailer',
                'expected' => 'Jobs Boards Mailer',
            ],
            'AWS_ACCESS_KEY_ID' => [
                'configFile' => 'config/filesystems.php',
                'configPath' => 'disks.s3.key',
                'value' => 'aws-key-proof',
                'expected' => 'aws-key-proof',
            ],
            'AWS_SECRET_ACCESS_KEY' => [
                'configFile' => 'config/filesystems.php',
                'configPath' => 'disks.s3.secret',
                'value' => 'aws-secret-proof',
                'expected' => 'aws-secret-proof',
            ],
            'AWS_DEFAULT_REGION' => [
                'configFile' => 'config/filesystems.php',
                'configPath' => 'disks.s3.region',
                'value' => 'ap-east-1',
                'expected' => 'ap-east-1',
            ],
            'AWS_BUCKET' => [
                'configFile' => 'config/filesystems.php',
                'configPath' => 'disks.s3.bucket',
                'value' => 'jobs-boards-proof',
                'expected' => 'jobs-boards-proof',
            ],
            'AWS_USE_PATH_STYLE_ENDPOINT' => [
                'configFile' => 'config/filesystems.php',
                'configPath' => 'disks.s3.use_path_style_endpoint',
                'value' => 'true',
                'expected' => true,
            ],
            'CANONICAL_AUDIT_AUTH_SERVICE_SECRET' => [
                'configFile' => 'config/canonical_audit_ingestion.php',
                'configPath' => 'callers.auth-service.secret',
                'value' => 'audit-secret-proof',
                'expected' => 'audit-secret-proof',
            ],
        ];
    }

    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_current_env_example_proof_subset_has_approved_map_records_and_expected_actions(): void
    {
        $envExampleKeys = $this->loadEnvExampleKeys();
        $mapping = $this->loadMapping();
        $proofSubset = array_merge(array_keys(self::consumerProofCases()), self::REMOVABLE_PLACEHOLDERS);

        foreach ($proofSubset as $entryName) {
            $this->assertContains($entryName, $envExampleKeys, sprintf('Expected "%s" to remain present in .env.example for the PR2 proof subset.', $entryName));
            $this->assertArrayHasKey($entryName, $mapping, sprintf('Expected "%s" to have a proof record in app-env-map.json.', $entryName));
            $this->assertSame($entryName, $mapping[$entryName]['name'] ?? null, sprintf('Expected "%s" mapping name to match its key.', $entryName));
            $this->assertIsString($mapping[$entryName]['consumerProof'] ?? null, sprintf('Expected "%s" to define consumerProof.', $entryName));
            $this->assertNotSame('', trim((string) ($mapping[$entryName]['consumerProof'] ?? '')), sprintf('Expected "%s" to define non-empty consumerProof.', $entryName));
            $this->assertIsString($mapping[$entryName]['ownerProof'] ?? null, sprintf('Expected "%s" to define ownerProof.', $entryName));
            $this->assertNotSame('', trim((string) ($mapping[$entryName]['ownerProof'] ?? '')), sprintf('Expected "%s" to define non-empty ownerProof.', $entryName));
        }

        foreach (self::ADVANCED_ONLY_ENTRIES as $entryName) {
            $this->assertSame(
                'advanced-doc',
                $mapping[$entryName]['templateAction'] ?? null,
                sprintf('Expected "%s" to be marked advanced-only in app-env-map.json.', $entryName),
            );
        }

        foreach (self::REMOVABLE_PLACEHOLDERS as $entryName) {
            $this->assertSame(
                'remove-normal',
                $mapping[$entryName]['templateAction'] ?? null,
                sprintf('Expected "%s" to be marked removable in app-env-map.json.', $entryName),
            );
        }
    }

    public function test_laravel_config_consumer_proof_subset_uses_live_config_consumers(): void
    {
        foreach (self::consumerProofCases() as $envKey => $case) {
            $resolved = $this->withScopedEnv([$envKey => $case['value']], fn (): mixed => data_get(
                require $this->repoRoot.'/'.$case['configFile'],
                $case['configPath'],
            ));

            $this->assertSame(
                $case['expected'],
                $resolved,
                sprintf(
                    'Expected "%s" to affect %s -> %s through the real Laravel config consumer.',
                    $envKey,
                    $case['configFile'],
                    $case['configPath'],
                ),
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadMapping(): array
    {
        $path = $this->repoRoot.'/ops/bootstrap/app-env-map.json';

        $this->assertFileExists($path);

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)['mappings'] ?? [];
    }

    /**
     * @return list<string>
     */
    private function loadEnvExampleKeys(): array
    {
        $lines = file($this->repoRoot.'/.env.example', FILE_IGNORE_NEW_LINES);
        $keys = [];

        foreach ($lines as $line) {
            if (! is_string($line) || ! preg_match('/^([A-Z][A-Z0-9_]+)=/', $line, $matches)) {
                continue;
            }

            $keys[] = $matches[1];
        }

        return $keys;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function withScopedEnv(array $values, callable $callback): mixed
    {
        $original = [];

        foreach ($values as $key => $value) {
            $original[$key] = getenv($key);
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        try {
            return $callback();
        } finally {
            foreach ($values as $key => $_) {
                $previous = $original[$key];

                if ($previous === false) {
                    putenv($key);
                    unset($_ENV[$key], $_SERVER[$key]);
                    continue;
                }

                putenv(sprintf('%s=%s', $key, $previous));
                $_ENV[$key] = $previous;
                $_SERVER[$key] = $previous;
            }
        }
    }
}
