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

    public function test_install_system_step_surfaces_system_requirements_and_refresh_action(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/install/steps/system.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('System Requirements', $contents);
        $this->assertStringContainsString('database', strtolower($contents));
        $this->assertStringContainsString('storage', strtolower($contents));
        $this->assertStringContainsString('cache', strtolower($contents));
        $this->assertStringContainsString('wire:click="refreshSystemChecks"', $contents);
    }

    public function test_install_security_step_uses_livewire_continue_and_no_placeholder_qr_copy(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/install/steps/security.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('wire:click="nextStep"', $contents);
        $this->assertStringNotContainsString('@click="tryNextStep"', $contents);
        $this->assertStringNotContainsString('Use the manual entry key below', $contents);
    }

    public function test_main_javascript_bundle_does_not_include_legacy_install_wizard(): void
    {
        $appJsPath = dirname(__DIR__, 2).'/resources/js/app.js';
        $legacyInstallPath = dirname(__DIR__, 2).'/resources/js/install.js';
        $contents = file_get_contents($appJsPath);

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('import "./install";', $contents);
        $this->assertFileDoesNotExist($legacyInstallPath);
    }
}
