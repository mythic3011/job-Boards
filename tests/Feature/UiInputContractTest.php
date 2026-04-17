<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class UiInputContractTest extends TestCase
{
    public function test_input_component_supports_explicit_id_and_theme_control_surface(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/input.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("'id' => null", $contents);
        $this->assertStringContainsString("\$inputId = \$id ?? \$name ?? 'input-'", $contents);
        $this->assertStringContainsString('theme-input block w-full rounded-xl border', $contents);
        $this->assertStringContainsString('theme-input-error', $contents);
        $this->assertStringContainsString('theme-input-disabled', $contents);
    }

    public function test_input_component_preserves_accessibility_wiring_for_help_and_errors(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/input.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('aria-invalid', $contents);
        $this->assertStringContainsString('aria-describedby', $contents);
        $this->assertStringContainsString('<x-ui.form-help', $contents);
        $this->assertStringContainsString('<x-ui.form-error', $contents);
    }
}
