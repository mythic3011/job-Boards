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
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('bg-white', $contents);
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
}
