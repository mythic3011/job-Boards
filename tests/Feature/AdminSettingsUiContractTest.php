<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminSettingsUiContractTest extends TestCase
{
    public function test_admin_settings_page_uses_theme_aware_toggle_cards_and_confirmation_modal(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/settings/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-panel', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-modal-surface', $contents);
        $this->assertStringContainsString('theme-button', $contents);
        $this->assertStringContainsString('theme-input', $contents);
        $this->assertStringNotContainsString('bg-white', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('border-gray-200', $contents);
    }
}
