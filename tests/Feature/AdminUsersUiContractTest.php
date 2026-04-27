<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminUsersUiContractTest extends TestCase
{
    public function test_admin_users_page_uses_theme_aware_status_chips_and_modal_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/users/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-hero-surface', $contents);
        $this->assertStringContainsString('theme-hero-eyebrow', $contents);
        $this->assertStringContainsString('theme-table-shell', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-alert-success', $contents);
        $this->assertStringContainsString('theme-alert-warning', $contents);
        $this->assertStringContainsString('theme-alert-error', $contents);
        $this->assertStringContainsString('theme-modal-surface', $contents);
        $this->assertStringNotContainsString('bg-red-100', $contents);
        $this->assertStringNotContainsString('bg-green-100', $contents);
        $this->assertStringNotContainsString('hover:bg-red-50', $contents);
        $this->assertStringNotContainsString('bg-white/5', $contents);
        $this->assertStringNotContainsString('border-white/10', $contents);
        $this->assertStringNotContainsString('text-indigo-200/80', $contents);
    }

    public function test_admin_users_reset_link_modal_uses_data_driven_copy_feedback(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/users/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-copy-button', $contents);
        $this->assertStringContainsString('data-copy-text="{{ $resetUrl }}"', $contents);
        $this->assertStringNotContainsString('navigator.clipboard.writeText', $contents);
        $this->assertStringNotContainsString('setTimeout(() => copied = false, 2000)', $contents);
    }

    public function test_admin_users_operator_notes_are_collapsible_with_accessible_toggle(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/users/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('aria-controls="users-operator-notes"', $contents);
        $this->assertStringContainsString(":aria-expanded=\"notesOpen.toString()\"", $contents);
        $this->assertStringContainsString("x-text=\"notesOpen ? 'Hide notes' : 'Show notes'\"", $contents);
        $this->assertStringContainsString('Target account', $contents);
        $this->assertStringContainsString('Login ID:', $contents);
        $this->assertStringContainsString('wire:confirm="Unlock {{ $user->nickname }}? This restores sign-in access immediately."', $contents);
        $this->assertStringContainsString('wire:confirm="Lock {{ $user->nickname }}? This blocks sign-in until an operator unlocks the account."', $contents);
        $this->assertStringContainsString('wire:confirm="Generate password reset link for {{ $user->nickname }}? Share only through a verified handoff channel."', $contents);
        $this->assertStringContainsString('Lock blocks sign-in immediately. Reset creates a sensitive one-time link.', $contents);
    }

    public function test_admin_users_index_prioritizes_full_width_table_over_persistent_sidebar(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/users/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("number_format(\$stats['total_users']).' users", $contents);
        $this->assertStringContainsString("number_format(\$stats['admin_users']).' admins", $contents);
        $this->assertStringNotContainsString('xl:grid-cols-[minmax(0,1.8fr)_minmax(320px,1fr)]', $contents);
        $this->assertStringNotContainsString('Access & Risk', $contents);
        $this->assertStringNotContainsString('theme-hero-card', $contents);
        $this->assertStringNotContainsString('High-level account posture', $contents);
    }

    public function test_admin_users_action_controls_have_visible_interaction_states(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/users/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-action-control', $contents);
        $this->assertStringContainsString('hover:', $contents);
        $this->assertStringContainsString('focus-visible:', $contents);
        $this->assertStringContainsString('active:', $contents);
        $this->assertStringContainsString('disabled:', $contents);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $contents);
    }
}
