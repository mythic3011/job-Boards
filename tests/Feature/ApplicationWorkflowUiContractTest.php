<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class ApplicationWorkflowUiContractTest extends TestCase
{
    public function test_application_create_page_uses_theme_aware_upload_and_editor_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/applications/create.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-link', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('bg-gray-50', $contents);
    }

    public function test_application_detail_page_uses_theme_aware_modal_and_content_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/applications/show.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-modal-surface', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-link', $contents);
        $this->assertStringContainsString('Decision target:', $contents);
        $this->assertStringContainsString('sets the application status to', $contents);
        $this->assertStringContainsString('note is visible to the applicant', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('bg-white', $contents);
    }

    public function test_applications_index_does_not_render_dead_new_message_badge_logic(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/applications/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('application_new_message_', $contents);
        $this->assertStringNotContainsString('New message', $contents);
        $this->assertStringContainsString('wire:click="clearFilters"', $contents);
        $this->assertStringNotContainsString('wire:click="$set(\'search\', \'\'); $set(\'statusFilter\', \'\')"', $contents);
        $this->assertStringContainsString('likeOperator()', $contents);
        $this->assertStringNotContainsString("where('title', 'ilike'", $contents);
        $this->assertStringNotContainsString("where('nickname', 'ilike'", $contents);
    }

    public function test_file_upload_component_uses_theme_aware_dropzone_tokens(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/file-upload.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringNotContainsString('border-gray-300', $contents);
        $this->assertStringNotContainsString('text-gray-700', $contents);
    }

    public function test_application_create_submit_button_exposes_visible_loading_feedback(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/applications/create.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $contents);
        $this->assertStringContainsString('wire:target="submit"', $contents);
        $this->assertStringContainsString('Submitting...', $contents);
        $this->assertStringContainsString('animate-spin', $contents);
        $this->assertStringContainsString('your account avatar is updated across profile and application views after submit', $contents);
        $this->assertStringContainsString('Uploading a CV does not change your profile photo', $contents);
    }
}
