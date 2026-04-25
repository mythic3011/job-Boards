<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class UiFeedbackJsContractTest extends TestCase
{
    public function test_app_bundle_imports_ui_feedback_helpers(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/js/app.js');

        $this->assertIsString($contents);
        $this->assertStringContainsString('import "./components/ui-feedback";', $contents);
    }

    public function test_ui_feedback_helpers_bind_copy_alert_and_infinite_scroll_contracts(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/ui-feedback.js');

        $this->assertIsString($contents);
        $this->assertStringContainsString('[data-copy-button]', $contents);
        $this->assertStringContainsString('[data-alert-surface]', $contents);
        $this->assertStringContainsString('[data-infinite-pagination]', $contents);
        $this->assertStringContainsString('window.initInfinitePagination(element);', $contents);
    }
}
