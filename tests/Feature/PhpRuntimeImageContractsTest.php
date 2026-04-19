<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class PhpRuntimeImageContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_shared_php_runtime_image_refreshes_packages_before_installing_php(): void
    {
        $dockerfileContents = file_get_contents($this->repoRoot.'/docker/Dockerfile.sail');

        $this->assertIsString($dockerfileContents);
        $this->assertStringContainsString('apt-get upgrade -y', $dockerfileContents);
        $this->assertStringContainsString('php8.5-fpm', $dockerfileContents);
    }

    public function test_laravel_test_and_queue_worker_both_build_from_the_shared_php_runtime_image(): void
    {
        $composeContents = file_get_contents($this->repoRoot.'/compose.yaml');
        $appComposeContents = file_get_contents($this->repoRoot.'/compose.app.yml');

        $this->assertIsString($composeContents);
        $this->assertIsString($appComposeContents);
        $this->assertStringContainsString("laravel.test:\n", $composeContents);
        $this->assertStringContainsString("queue-worker:\n", $composeContents);
        $this->assertStringContainsString('dockerfile: docker/Dockerfile.sail', $composeContents);
        $this->assertStringContainsString("laravel.test:\n", $appComposeContents);
        $this->assertStringContainsString("queue-worker:\n", $appComposeContents);
        $this->assertStringContainsString('dockerfile: docker/Dockerfile.sail', $appComposeContents);
    }

    public function test_shared_php_runtime_builds_do_not_configure_local_builder_cache_exports(): void
    {
        $composeContents = file_get_contents($this->repoRoot.'/compose.yaml');
        $appComposeContents = file_get_contents($this->repoRoot.'/compose.app.yml');

        $this->assertIsString($composeContents);
        $this->assertIsString($appComposeContents);
        $this->assertStringNotContainsString('cache_from:', $composeContents);
        $this->assertStringNotContainsString('cache_to:', $composeContents);
        $this->assertStringNotContainsString('type=local,src=.docker/buildx-cache/php-runtime', $composeContents);
        $this->assertStringNotContainsString('type=local,dest=.docker/buildx-cache/php-runtime,mode=max', $composeContents);
        $this->assertStringNotContainsString('cache_from:', $appComposeContents);
        $this->assertStringNotContainsString('cache_to:', $appComposeContents);
        $this->assertStringNotContainsString('type=local,src=.docker/buildx-cache/php-runtime', $appComposeContents);
        $this->assertStringNotContainsString('type=local,dest=.docker/buildx-cache/php-runtime,mode=max', $appComposeContents);
    }
}
