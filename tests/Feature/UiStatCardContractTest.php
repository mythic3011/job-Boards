<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class UiStatCardContractTest extends TestCase
{
    public function test_stat_card_component_uses_theme_tokens_for_copy_and_value(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/stat-card.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('<x-ui.card', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringNotContainsString('text-gray-600', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
    }
}
