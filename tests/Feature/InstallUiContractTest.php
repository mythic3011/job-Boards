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
        $this->assertStringContainsString('Check DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD', $contents);
        $this->assertStringContainsString('Ensure storage and bootstrap/cache are writable', $contents);
        $this->assertStringContainsString('Verify cache driver config', $contents);
    }

    public function test_install_security_step_uses_livewire_continue_and_no_placeholder_qr_copy(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/install/steps/security.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('wire:click="nextStep"', $contents);
        $this->assertStringNotContainsString('@click="tryNextStep"', $contents);
        $this->assertStringNotContainsString('Use the manual entry key below', $contents);
        $this->assertStringContainsString('x-data="{ showCodes: false }"', $contents);
        $this->assertStringContainsString('$this->recoveryCodesDownloadHref', $contents);
        $this->assertStringNotContainsString('downloadCodes()', $contents);
        $this->assertStringNotContainsString('async copySecret()', $contents);
        $this->assertStringContainsString('data-copy-button', $contents);
        $this->assertStringContainsString('data-copy-text="{{ $twoFactorSecret }}"', $contents);
        $this->assertStringNotContainsString('navigator.clipboard.writeText($event.currentTarget.dataset.secret)', $contents);
    }

    public function test_install_step_actions_expose_visible_loading_feedback(): void
    {
        $account = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/install/steps/account.blade.php');
        $system = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/install/steps/system.blade.php');
        $security = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/install/steps/security.blade.php');
        $review = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/install/steps/review.blade.php');

        $this->assertIsString($account);
        $this->assertIsString($system);
        $this->assertIsString($security);
        $this->assertIsString($review);

        $this->assertStringContainsString('wire:loading.attr="disabled"', $account);
        $this->assertStringContainsString('wire:target="nextStep"', $account);
        $this->assertStringContainsString('Continuing...', $account);
        $this->assertStringContainsString('animate-spin', $account);

        $this->assertStringContainsString('wire:target="refreshSystemChecks"', $system);
        $this->assertStringContainsString('Checking...', $system);
        $this->assertStringContainsString('wire:target="nextStep"', $system);
        $this->assertStringContainsString('Continuing...', $system);
        $this->assertStringContainsString('animate-spin', $system);

        $this->assertStringContainsString('wire:target="testOTP"', $security);
        $this->assertStringContainsString('Verifying...', $security);
        $this->assertStringContainsString('wire:target="nextStep"', $security);
        $this->assertStringContainsString('Continuing...', $security);
        $this->assertStringContainsString('animate-spin', $security);

        $this->assertStringContainsString('wire:target="complete"', $review);
        $this->assertStringContainsString('Installing...', $review);
        $this->assertStringContainsString('animate-spin', $review);
        $this->assertStringContainsString('Includes demo users, job listings, and applications', $review);
        $this->assertStringContainsString('remove demo data later from Admin Settings using the demo cleanup action', $review);
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

    public function test_install_wizard_completion_does_not_depend_on_livewire_navigate_plugin(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/app/Livewire/Install/Wizard.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("\$this->redirect('/login');", $contents);
        $this->assertStringNotContainsString("navigate: true", $contents);
    }
}
