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
    }

    public function test_password_page_surfaces_security_status_and_password_checklist(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/profile/password.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Security Status', $contents);
        $this->assertStringContainsString('Password Checklist', $contents);
    }

    public function test_two_factor_page_surfaces_security_overview_and_quick_actions(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/profile/two-factor.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Security Overview', $contents);
        $this->assertStringContainsString('Quick Actions', $contents);
    }
}
