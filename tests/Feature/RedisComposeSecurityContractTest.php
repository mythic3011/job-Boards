<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class RedisComposeSecurityContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_compose_files_require_redis_init_service_to_generate_password_protected_runtime_config(): void
    {
        foreach (['compose.yaml', 'compose.app.yml'] as $composeFile) {
            $contents = file_get_contents($this->repoRoot.'/'.$composeFile);

            $this->assertIsString($contents);
            $this->assertStringContainsString('redis-config-init:', $contents);
            $this->assertStringContainsString('cat > /runtime/redis.conf <<EOF', $contents);
            $this->assertStringContainsString('requirepass $${REDIS_PASSWORD_VALUE}', $contents);
            $this->assertStringContainsString('redis-runtime:/runtime', $contents);
        }
    }

    public function test_compose_files_start_redis_with_generated_config_and_authenticated_healthcheck(): void
    {
        foreach (['compose.yaml', 'compose.app.yml'] as $composeFile) {
            $contents = file_get_contents($this->repoRoot.'/'.$composeFile);

            $this->assertIsString($contents);
            $this->assertMatchesRegularExpression(
                '/redis:\n(?:(?:\s{2,}).*\n)*?\s+command:\n\s+- redis-server\n\s+- \/run\/redis\/redis\.conf/m',
                $contents
            );
            $this->assertStringContainsString("redis-config-init:\n", $contents);
            $this->assertMatchesRegularExpression(
                '/redis:\n(?:(?:\s{2,}).*\n)*?\s+depends_on:\n\s+redis-config-init:\n\s+condition:\s+service_completed_successfully/m',
                $contents
            );
            $this->assertMatchesRegularExpression(
                '/redis-cli -a \\\\"\\$\\$\\{REDIS_PASSWORD\\}\\\\" ping \| grep -q PONG/',
                $contents
            );
            $this->assertStringContainsString('redis-runtime:/run/redis:ro', $contents);
        }
    }

    public function test_laravel_and_queue_services_receive_redis_password_env_for_authenticated_connections(): void
    {
        foreach (['compose.yaml', 'compose.app.yml'] as $composeFile) {
            $contents = file_get_contents($this->repoRoot.'/'.$composeFile);

            $this->assertIsString($contents);
            $this->assertGreaterThanOrEqual(
                3,
                substr_count($contents, 'REDIS_PASSWORD: ${REDIS_PASSWORD:-jobs_redis_local_password}')
            );
        }
    }
}
