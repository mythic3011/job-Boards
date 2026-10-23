<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShpDeploymentContractTest extends TestCase
{
    public function test_shp_deployment_keeps_php_85_and_private_app_surface(): void
    {
        $root = dirname(__DIR__, 2);
        $dockerfile = file_get_contents($root.'/deploy/shp/Dockerfile');
        $compose = file_get_contents($root.'/plugins/job-boards-app/compose.yaml');
        $bootstrap = file_get_contents($root.'/deploy/shp/bootstrap.sh');
        $ignore = file_get_contents($root.'/deploy/shp/Dockerfile.dockerignore');

        $this->assertIsString($dockerfile);
        $this->assertIsString($compose);
        $this->assertIsString($bootstrap);
        $this->assertIsString($ignore);

        $this->assertStringContainsString('FROM php:8.5-apache-bookworm', $dockerfile);
        $this->assertStringContainsString('/var/www/html/public', $dockerfile);
        $this->assertStringContainsString('migrate --force --no-interaction', $compose);
        $this->assertStringContainsString('APP_DEBUG: "false"', $compose);
        $this->assertStringContainsString('TRUSTED_PROXIES: REMOTE_ADDR', $compose);
        $this->assertStringContainsString('BIND_ADDRESS=127.0.0.1', $bootstrap);
        $this->assertStringContainsString('.env', $ignore);
        $this->assertStringNotContainsString('ports:', $compose);
        $this->assertStringNotContainsString('migrate:fresh', $compose);
        $this->assertStringNotContainsString('|| true', $compose);
    }
}
