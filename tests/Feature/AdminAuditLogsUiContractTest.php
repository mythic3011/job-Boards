<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminAuditLogsUiContractTest extends TestCase
{
    public function test_admin_audit_logs_page_uses_theme_aware_filter_and_table_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/audit-logs.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-input-shell', $contents);
        $this->assertStringContainsString('theme-input', $contents);
        $this->assertStringContainsString('theme-table-shell', $contents);
        $this->assertStringContainsString('theme-table-head', $contents);
        $this->assertStringContainsString('theme-table-divider', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-link', $contents);
        $this->assertStringNotContainsString('bg-white', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('border-gray-200', $contents);
    }
}
