<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminApplicationsUiContractTest extends TestCase
{
    public function test_admin_applications_page_uses_review_queue_sections(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/applications/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Application queue', $contents);
        $this->assertStringContainsString('Review posture', $contents);
        $this->assertStringContainsString('Search submitted applications', $contents);
        $this->assertStringContainsString('Review Filters', $contents);
    }

    public function test_admin_applications_table_uses_avatar_and_compact_review_columns(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/applications/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('<x-ui.avatar', $contents);
        $this->assertStringContainsString('Pending Review', $contents);
        $this->assertStringContainsString('CV Attached', $contents);
        $this->assertStringContainsString('Application', $contents);
        $this->assertStringContainsString('Review & Timeline', $contents);
        $this->assertStringContainsString('Open review', $contents);
        $this->assertStringContainsString('CV file', $contents);
        $this->assertStringNotContainsString('>Submitted<', $contents);
    }

    public function test_admin_applications_page_uses_theme_aware_filters_and_queue_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/applications/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-panel', $contents);
        $this->assertStringContainsString('theme-input-shell', $contents);
        $this->assertStringContainsString('theme-input', $contents);
        $this->assertStringContainsString('theme-table-shell', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringNotContainsString('rounded-2xl border border-gray-200 bg-white', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
    }
}
