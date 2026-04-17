<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class UiEmptyStateContractTest extends TestCase
{
    public function test_empty_state_component_uses_refined_surface_contract(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/empty-state.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('<x-ui.card', $contents);
        $this->assertStringContainsString('tone="subtle"', $contents);
        $this->assertStringContainsString('data-empty-state', $contents);
        $this->assertStringContainsString('max-w-2xl mx-auto', $contents);
    }

    public function test_empty_state_component_exposes_consistent_icon_and_action_layouts(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/empty-state.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-empty-icon', $contents);
        $this->assertStringContainsString('mt-6 flex flex-wrap items-center justify-center gap-3', $contents);
        $this->assertStringContainsString('theme-text-muted text-base leading-7', $contents);
    }
}
