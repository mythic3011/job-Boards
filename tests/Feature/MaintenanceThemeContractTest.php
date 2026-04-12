<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class MaintenanceThemeContractTest extends TestCase
{
    public function test_maintenance_error_page_uses_theme_aware_status_and_action_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/errors/maintenance.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-panel', $contents);
        $this->assertStringContainsString('theme-alert-info', $contents);
        $this->assertStringContainsString('theme-button', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringNotContainsString('bg-white', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
    }

    public function test_maintenance_modal_uses_theme_aware_overlay_and_action_tokens(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/components/maintenance-alert.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-modal-surface', $contents);
        $this->assertStringContainsString('theme-alert-warning', $contents);
        $this->assertStringContainsString('theme-button', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringNotContainsString('bg-white', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
    }
}
