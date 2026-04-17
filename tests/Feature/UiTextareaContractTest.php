<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class UiTextareaContractTest extends TestCase
{
    public function test_textarea_component_matches_theme_control_contract_and_explicit_id_support(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/textarea.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("'id' => null", $contents);
        $this->assertStringContainsString("\$inputId = \$id ?? \$name ?? 'textarea-'", $contents);
        $this->assertStringContainsString('theme-input block w-full rounded-xl border', $contents);
        $this->assertStringContainsString('resize-y', $contents);
        $this->assertStringContainsString('theme-input-error', $contents);
        $this->assertStringContainsString('theme-input-disabled', $contents);
    }

    public function test_textarea_component_preserves_help_error_and_describedby_contract(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/textarea.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('aria-invalid', $contents);
        $this->assertStringContainsString('aria-describedby', $contents);
        $this->assertStringContainsString('<x-ui.form-help', $contents);
        $this->assertStringContainsString('<x-ui.form-error', $contents);
    }
}
