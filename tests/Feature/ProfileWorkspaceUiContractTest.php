<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class ProfileWorkspaceUiContractTest extends TestCase
{
    public function test_profile_pages_share_a_workspace_navigation_partial(): void
    {
        $show = file_get_contents(dirname(__DIR__, 2).'/resources/views/profile/show.blade.php');
        $edit = file_get_contents(dirname(__DIR__, 2).'/resources/views/profile/edit.blade.php');
        $password = file_get_contents(dirname(__DIR__, 2).'/resources/views/profile/password.blade.php');
        $twoFactor = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/profile/two-factor.blade.php');
        $partial = file_get_contents(dirname(__DIR__, 2).'/resources/views/profile/partials/workspace-nav.blade.php');

        $this->assertIsString($show);
        $this->assertIsString($edit);
        $this->assertIsString($password);
        $this->assertIsString($twoFactor);
        $this->assertIsString($partial);

        $this->assertStringContainsString("profile.partials.workspace-nav", $show);
        $this->assertStringContainsString("profile.partials.workspace-nav", $edit);
        $this->assertStringContainsString("profile.partials.workspace-nav", $password);
        $this->assertStringContainsString("profile.partials.workspace-nav", $twoFactor);

        $this->assertStringContainsString("route('profile.show')", $partial);
        $this->assertStringContainsString("route('profile.edit')", $partial);
        $this->assertStringContainsString("route('profile.password')", $partial);
        $this->assertStringContainsString("route('profile.two-factor')", $partial);
    }

    public function test_profile_edit_page_surfaces_account_summary_and_security_shortcuts(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/profile/edit.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Account Summary', $contents);
        $this->assertStringContainsString('Security Shortcuts', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-signal-success', $contents);
        $this->assertStringContainsString('theme-signal-warning', $contents);
        $this->assertStringContainsString('theme-alert-warning', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('bg-gray-50', $contents);
        $this->assertStringNotContainsString('text-green-700', $contents);
        $this->assertStringNotContainsString('text-yellow-700', $contents);
        $this->assertStringNotContainsString('bg-yellow-50', $contents);
    }

    public function test_profile_show_page_surfaces_workspace_overview_and_security_posture(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/profile/show.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Workspace Overview', $contents);
        $this->assertStringContainsString('Identity Snapshot', $contents);
        $this->assertStringContainsString('Security Posture', $contents);
        $this->assertStringContainsString('data-profile-overview-hero', $contents);
        $this->assertStringContainsString('data-profile-identity-login-id', $contents);
        $this->assertStringContainsString('Member since', $contents);
        $this->assertStringNotContainsString('Workspace tabs', $contents);
        $this->assertStringNotContainsString('Recommended Next Steps', $contents);
        $this->assertStringNotContainsString('Quick Actions', $contents);
        $this->assertStringNotContainsString('data-profile-overview-actions', $contents);
        $this->assertStringNotContainsString('data-profile-overview-metrics', $contents);
        $this->assertMatchesRegularExpression(
            '/(<[^>]*data-profile-identity-login-id[^>]*class="[^"]*break-all[^"]*"|<[^>]*class="[^"]*break-all[^"]*"[^>]*data-profile-identity-login-id)/',
            $contents
        );
        $this->assertStringContainsString('theme-alert-success', $contents);
        $this->assertStringContainsString('theme-alert-warning', $contents);
        $this->assertStringNotContainsString('bg-green-50', $contents);
        $this->assertStringNotContainsString('text-green-700', $contents);
        $this->assertStringNotContainsString('border-green-200', $contents);
    }

    public function test_password_page_surfaces_security_status_and_password_checklist(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/profile/password.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Security Status', $contents);
        $this->assertStringContainsString('Password Checklist', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-link', $contents);
        $this->assertStringContainsString('theme-signal-success', $contents);
        $this->assertStringContainsString('theme-dot-success', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('border-gray-200', $contents);
        $this->assertStringNotContainsString('text-green-700', $contents);
        $this->assertStringNotContainsString('bg-green-500', $contents);
    }

    public function test_two_factor_page_surfaces_security_overview_and_quick_actions(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/profile/two-factor.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Security Overview', $contents);
        $this->assertStringContainsString('Quick Actions', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-signal-success', $contents);
        $this->assertStringContainsString('theme-signal-info', $contents);
        $this->assertStringContainsString('theme-signal-warning', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('bg-gray-50', $contents);
        $this->assertStringNotContainsString('text-green-700', $contents);
        $this->assertStringNotContainsString('text-blue-700', $contents);
        $this->assertStringNotContainsString('text-yellow-700', $contents);
    }

    public function test_two_factor_page_actions_expose_visible_loading_feedback(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/profile/two-factor.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('wire:target="enable2FA"', $contents);
        $this->assertStringContainsString('Setting up...', $contents);
        $this->assertStringContainsString('wire:target="disable2FA"', $contents);
        $this->assertStringContainsString('Disabling...', $contents);
        $this->assertStringContainsString('wire:target="regenerateRecoveryCodes"', $contents);
        $this->assertStringContainsString('Replacing...', $contents);
        $this->assertStringContainsString('animate-spin', $contents);
    }

    public function test_workspace_navigation_partial_uses_theme_aware_tabs_instead_of_light_only_shell(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/profile/partials/workspace-nav.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-panel', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-pill', $contents);
        $this->assertStringContainsString('theme-alert-warning', $contents);
        $this->assertStringNotContainsString('bg-white', $contents);
        $this->assertStringNotContainsString('text-gray-600', $contents);
        $this->assertStringNotContainsString('bg-yellow-50', $contents);
        $this->assertStringNotContainsString('text-yellow-700', $contents);
    }
}
