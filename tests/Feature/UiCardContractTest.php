<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class UiCardContractTest extends TestCase
{
    public function test_card_component_uses_refined_surface_defaults(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/card.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("'tone' => 'default'", $contents);
        $this->assertStringContainsString("theme-panel rounded-2xl border", $contents);
        $this->assertStringContainsString("'subtle' => 'theme-panel-subtle rounded-2xl border'", $contents);
        $this->assertStringContainsString('data-card-surface', $contents);
    }

    public function test_card_component_preserves_hover_capability_on_refined_surface(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/card.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("transition-shadow duration-200 hover:shadow-md", $contents);
    }
}
