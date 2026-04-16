<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminDashboardUiContractTest extends TestCase
{
    public function test_admin_dashboard_uses_refined_operational_sections(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/dashboard.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Operational snapshot', $contents);
        $this->assertStringContainsString('Command Center', $contents);
        $this->assertStringContainsString('Platform Snapshot', $contents);
        $this->assertStringContainsString('Security Pulse', $contents);
        $this->assertStringContainsString('Recent Activity', $contents);
    }

    public function test_admin_dashboard_surfaces_action_oriented_summary_labels(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/dashboard.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Pending Review', $contents);
        $this->assertStringContainsString('Failed Sign-ins Today', $contents);
        $this->assertStringContainsString('Suspicious Events', $contents);
    }

    public function test_admin_dashboard_badges_cover_canonical_auth_verify_events(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/dashboard.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("str_contains(\$eventType, 'denied') || \$eventType === 'audit.auth.verify.denied'", $contents);
        $this->assertStringContainsString("str_contains(\$eventType, 'login') || \$eventType === 'audit.auth.verify.success'", $contents);
    }
}
