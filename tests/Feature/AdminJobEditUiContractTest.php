<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminJobEditUiContractTest extends TestCase
{
    public function test_admin_job_edit_page_uses_theme_aware_alert_and_salary_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/jobs/edit.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-alert-error', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-input', $contents);
        $this->assertStringContainsString('<x-ui.button', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('ring-gray-300', $contents);
        $this->assertStringNotContainsString('bg-red-50', $contents);
    }
}
