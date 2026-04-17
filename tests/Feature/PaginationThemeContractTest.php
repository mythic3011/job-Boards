<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class PaginationThemeContractTest extends TestCase
{
    public function test_standard_pagination_views_use_theme_aware_shell_and_controls(): void
    {
        $tailwind = file_get_contents(dirname(__DIR__, 2).'/resources/views/vendor/pagination/tailwind.blade.php');
        $simple = file_get_contents(dirname(__DIR__, 2).'/resources/views/vendor/pagination/simple-tailwind.blade.php');

        $this->assertIsString($tailwind);
        $this->assertIsString($simple);

        foreach ([$tailwind, $simple] as $contents) {
            $this->assertStringContainsString('theme-panel', $contents);
            $this->assertStringContainsString('theme-text-muted', $contents);
            $this->assertStringContainsString('theme-text-strong', $contents);
            $this->assertStringContainsString('theme-button', $contents);
            $this->assertStringContainsString('theme-button-outline', $contents);
            $this->assertStringNotContainsString('bg-white', $contents);
            $this->assertStringNotContainsString('text-gray-', $contents);
        }
    }
}
