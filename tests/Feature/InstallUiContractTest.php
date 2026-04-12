<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class InstallUiContractTest extends TestCase
{
    public function test_install_entry_uses_shared_base_layout_and_livewire_root(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/install/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('<x-layouts.base', $contents);
        $this->assertStringContainsString('@livewire(\'install.wizard\')', $contents);
        $this->assertStringContainsString('data-install-livewire-root', $contents);
    }

    public function test_install_wizard_uses_theme_aware_shell_contract(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/install/wizard.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-install-livewire-root', $contents);
        $this->assertStringContainsString('theme-auth-emblem', $contents);
        $this->assertStringContainsString('theme-panel', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
    }

    public function test_install_javascript_does_not_hijack_livewire_installer_root(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/js/install.js');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-install-livewire-root', $contents);
        $this->assertStringContainsString('Livewire installer root detected', $contents);
    }
}
