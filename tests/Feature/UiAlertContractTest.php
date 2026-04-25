<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class UiAlertContractTest extends TestCase
{
    public function test_alert_component_only_auto_dismisses_success_by_default(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/alert.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("'autoDismiss' => null", $contents);
        $this->assertStringContainsString("'autoDismissMs' => 3000", $contents);
        $this->assertStringContainsString("\$shouldAutoDismiss = \$autoDismiss ?? (\$type === 'success');", $contents);
        $this->assertStringNotContainsString("in_array(\$type, ['success', 'error'])", $contents);
    }

    public function test_alert_component_exposes_stable_feedback_surface_contract(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/alert.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-alert-surface', $contents);
        $this->assertStringContainsString('data-auto-dismiss-ms="{{ $autoDismissMs }}"', $contents);
        $this->assertStringContainsString('data-alert-dismiss', $contents);
        $this->assertStringContainsString('rounded-2xl', $contents);
        $this->assertStringContainsString('theme-alert', $contents);
        $this->assertStringContainsString('transition-opacity duration-300', $contents);
        $this->assertStringNotContainsString('setTimeout(() => { show = false }, {{ $autoDismissMs }})', $contents);
        $this->assertStringNotContainsString('x-init=', $contents);
    }
}
