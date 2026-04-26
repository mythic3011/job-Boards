<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminAuditLogsUiContractTest extends TestCase
{
    public function test_audit_log_viewer_stats_and_badges_cover_canonical_auth_events(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/audit-logs.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("COUNT(CASE WHEN event_type IN ('login_failed', 'audit.auth.verify.denied') THEN 1 END) as failed_logins", $contents);
        $this->assertStringContainsString("COUNT(CASE WHEN event_type IN ('account_locked', 'audit.auth.locked') THEN 1 END) as locked_accounts", $contents);
        $this->assertStringContainsString("'bot_fingerprint_probe'", $contents);
        $this->assertStringContainsString("'honeypot.triggered'", $contents);
        $this->assertStringContainsString("str_contains(\$log->event_type, 'denied') || \$log->event_type === 'audit.auth.verify.denied'", $contents);
        $this->assertStringContainsString("str_contains(\$log->event_type, 'suspicious') || str_contains(\$log->event_type, 'probe') || str_starts_with(\$log->event_type, 'security.') || \$log->event_type === 'honeypot.triggered'", $contents);
        $this->assertStringContainsString("str_contains(\$log->event_type, 'login') || \$log->event_type === 'audit.auth.verify.success'", $contents);
    }

    public function test_audit_log_viewer_has_explicit_summary_contract_for_bot_and_honeypot_events(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/audit-logs.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("if (\$log->event_type === 'bot_fingerprint_probe')", $contents);
        $this->assertStringContainsString("if (\$log->event_type === 'honeypot.triggered')", $contents);
        $this->assertStringContainsString('Decision Context', $contents);
        $this->assertStringContainsString('No audit logs match the current filters. Clear or broaden filters to continue review.', $contents);
        $this->assertStringContainsString('No audit logs recorded yet. Sign-in and security events will appear here when activity starts.', $contents);
    }
}
