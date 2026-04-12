<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminUsersUiContractTest extends TestCase
{
    public function test_admin_users_page_uses_refined_operational_sections(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/users/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('User operations', $contents);
        $this->assertStringContainsString('Access & Risk', $contents);
        $this->assertStringContainsString('Directory', $contents);
        $this->assertStringContainsString('Search the user base', $contents);
    }

    public function test_admin_users_page_uses_avatar_component_and_summary_labels(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/users/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('<x-ui.avatar', $contents);
        $this->assertStringContainsString('2FA Enabled', $contents);
        $this->assertStringContainsString('Locked Accounts', $contents);
    }

    public function test_button_component_supports_warning_variant_for_moderation_actions(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/button.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("'warning' =>", $contents);
    }

    public function test_admin_users_table_uses_compact_datatable_actions_and_combined_security_columns(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/users/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Role & Access', $contents);
        $this->assertStringContainsString('Security', $contents);
        $this->assertStringContainsString('Quick actions', $contents);
        $this->assertStringContainsString('data-dropdown-panel', $contents);
        $this->assertStringContainsString('overflow-x-auto overflow-y-visible', $contents);
        $this->assertStringContainsString('min-w-[760px] w-full table-fixed', $contents);
    }

    public function test_admin_users_page_uses_theme_aware_directory_and_modal_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/users/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-panel', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-input-shell', $contents);
        $this->assertStringContainsString('theme-input', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringNotContainsString('rounded-2xl border border-gray-200 bg-white', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
    }
}
