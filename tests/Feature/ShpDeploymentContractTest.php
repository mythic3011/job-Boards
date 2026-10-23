<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShpDeploymentContractTest extends TestCase
{
    public function test_shp_deployment_enforces_php_patch_floor_and_local_first_install(): void
    {
        $root = dirname(__DIR__, 2);
        $dockerfile = file_get_contents($root.'/deploy/shp/Dockerfile');
        $compose = file_get_contents($root.'/plugins/job-boards-app/compose.yaml');
        $deploy = file_get_contents($root.'/deploy.sh');
        $check = file_get_contents($root.'/plugins/job-boards-app/hooks/check.sh');
        $ignore = file_get_contents($root.'/deploy/shp/Dockerfile.dockerignore');

        foreach ([$dockerfile, $compose, $deploy, $check, $ignore] as $content) {
            $this->assertIsString($content);
        }

        $this->assertStringContainsString('php:8.5.12-apache-bookworm@sha256:', $dockerfile);
        $this->assertStringContainsString('version_compare(PHP_VERSION, "8.5.12", "<")', $dockerfile);
        $this->assertStringContainsString('8.5.12', $check);
        $this->assertStringContainsString('BIND_ADDRESS=127.0.0.1', $deploy);
        $this->assertStringContainsString('JOB_BOARDS_ALLOW_PUBLIC_BIND', $deploy);
        $this->assertStringContainsString('Existing project volumes but JOB_BOARDS_APP_KEY is missing', $deploy);
        $this->assertStringNotContainsString('--ignore-unfixed', $deploy);
        $this->assertStringContainsString('dc build --pull app', $deploy);
        $this->assertStringContainsString('migrate --force --no-interaction', $compose);
        $this->assertStringContainsString('APP_DEBUG: "false"', $compose);
        $this->assertStringContainsString('TRUSTED_PROXIES: REMOTE_ADDR', $compose);
        $this->assertStringContainsString('.env', $ignore);
        $this->assertStringContainsString('.deploy', $ignore);
        $this->assertStringNotContainsString('ports:', $compose);
        $this->assertStringNotContainsString('migrate:fresh', $compose);
    }
}
