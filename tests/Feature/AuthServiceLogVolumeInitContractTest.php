<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AuthServiceLogVolumeInitContractTest extends TestCase
{
    public function test_primary_compose_initializes_auth_service_log_volume_permissions_before_starting_auth_service(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/compose.yaml');

        $this->assertIsString($contents);
        $this->assertStringContainsString("  auth-service-logs-init:\n", $contents);
        $this->assertStringContainsString('image: alpine:3.20', $contents);
        $this->assertStringContainsString('user: "0:0"', $contents);
        $this->assertStringContainsString('mkdir -p /var/log/auth-service && chown -R 100:101 /var/log/auth-service', $contents);
        $this->assertStringContainsString("      auth-service-logs-init:\n                condition: service_completed_successfully", $contents);
    }
}
