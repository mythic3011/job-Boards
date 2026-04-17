<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class InfiniteScrollPaginationUiContractTest extends TestCase
{
    public function test_infinite_scroll_pagination_uses_theme_aware_status_and_controls(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/infinite-scroll-pagination.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-pagination-progress', $contents);
        $this->assertStringContainsString('data-pagination-progress-bar', $contents);
        $this->assertStringContainsString('aria-live="polite"', $contents);
        $this->assertStringContainsString('data-pagination-remaining', $contents);
        $this->assertStringContainsString('theme-table-divider', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-button', $contents);
        $this->assertStringContainsString('theme-button-primary', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-pill', $contents);
        $this->assertStringNotContainsString('border-gray-100', $contents);
        $this->assertStringNotContainsString('bg-white', $contents);
    }
}
