<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminDashboardUiContractTest extends TestCase
{
    public function test_dashboard_service_counts_bot_and_honeypot_events_as_suspicious(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/app/Services/DashboardService.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("'bot_fingerprint_probe'", $contents);
        $this->assertStringContainsString("'honeypot.triggered'", $contents);
    }

    public function test_dashboard_service_includes_bot_and_honeypot_events_in_recent_activity(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/app/Services/DashboardService.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("'bot_fingerprint_probe'", $contents);
        $this->assertStringContainsString("'honeypot.triggered'", $contents);
        $this->assertStringContainsString("'setup.completed'", $contents);
    }

    public function test_admin_dashboard_uses_refined_operational_sections(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/dashboard.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Operational snapshot', $contents);
        $this->assertStringContainsString('Command Center', $contents);
        $this->assertStringContainsString('Platform Snapshot', $contents);
        $this->assertStringContainsString('Security Pulse', $contents);
        $this->assertStringContainsString('Recent Activity', $contents);
        $this->assertStringContainsString('x-ui.card class="grid grid-cols-1 gap-4 sm:grid-cols-2"', $contents);
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
        $this->assertStringContainsString("str_contains(\$eventType, 'suspicious') || str_contains(\$eventType, 'probe') || \$eventType === 'honeypot.triggered'", $contents);
        $this->assertStringContainsString("str_contains(\$eventType, 'login') || \$eventType === 'audit.auth.verify.success'", $contents);
    }

    public function test_admin_dashboard_activity_labels_cover_bot_and_honeypot_events(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/dashboard.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("'bot_fingerprint_probe' => 'Bot fingerprint probe'", $contents);
        $this->assertStringContainsString("'honeypot.triggered' => 'Honeypot triggered'", $contents);
    }
}
