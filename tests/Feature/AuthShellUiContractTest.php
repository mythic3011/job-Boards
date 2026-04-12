<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AuthShellUiContractTest extends TestCase
{
    public function test_primary_auth_pages_share_the_auth_shell_component(): void
    {
        $login = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/login.blade.php');
        $register = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/register.blade.php');
        $forgot = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/forgot-password.blade.php');
        $reset = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/reset-password.blade.php');

        $this->assertIsString($login);
        $this->assertIsString($register);
        $this->assertIsString($forgot);
        $this->assertIsString($reset);

        $this->assertStringContainsString('<x-auth.shell', $login);
        $this->assertStringContainsString('<x-auth.shell', $register);
        $this->assertStringContainsString('<x-auth.shell', $forgot);
        $this->assertStringContainsString('<x-auth.shell', $reset);
    }

    public function test_auth_shell_exposes_theme_aware_surface_contract(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/auth/shell.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-auth-shell', $contents);
        $this->assertStringContainsString('theme-auth-shell', $contents);
        $this->assertStringContainsString('theme-auth-emblem', $contents);
        $this->assertStringContainsString('data-auth-panel-copy', $contents);
    }

    public function test_login_page_surfaces_workspace_access_and_security_context(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/login.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Workspace Access', $contents);
        $this->assertStringContainsString('Security Notes', $contents);
        $this->assertStringContainsString('<x-ui.input', $contents);
    }

    public function test_register_page_uses_account_mode_cards_and_launch_checklist(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/register.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Choose Your Workspace', $contents);
        $this->assertStringContainsString('Individual Workspace', $contents);
        $this->assertStringContainsString('Company Workspace', $contents);
        $this->assertStringContainsString('Launch Checklist', $contents);
        $this->assertStringContainsString('type="radio"', $contents);
        $this->assertStringContainsString('data-workspace-option', $contents);
        $this->assertStringContainsString('data-workspace-indicator', $contents);
        $this->assertStringContainsString('for="user_type_individual"', $contents);
        $this->assertStringContainsString('for="user_type_company"', $contents);
        $this->assertStringContainsString('<x-ui.input', $contents);
    }

    public function test_stylesheet_defines_workspace_selection_accent_token(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        $this->assertIsString($contents);
        $this->assertStringContainsString('--app-accent-strong:', $contents);
    }
}
