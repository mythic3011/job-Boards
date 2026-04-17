<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class ErrorPageUiContractTest extends TestCase
{
    public function test_shared_error_page_component_uses_theme_aware_shell_and_surface(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/errors/page.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-error-shell', $contents);
        $this->assertStringContainsString('data-error-surface', $contents);
        $this->assertStringContainsString('theme-error-code', $contents);
        $this->assertStringContainsString('theme-panel', $contents);
    }

    public function test_error_templates_continue_using_the_shared_error_component(): void
    {
        $error404 = file_get_contents(dirname(__DIR__, 2).'/resources/views/errors/404.blade.php');
        $error500 = file_get_contents(dirname(__DIR__, 2).'/resources/views/errors/500.blade.php');
        $error503 = file_get_contents(dirname(__DIR__, 2).'/resources/views/errors/503.blade.php');

        $this->assertIsString($error404);
        $this->assertIsString($error500);
        $this->assertIsString($error503);

        $this->assertStringContainsString('<x-errors.page', $error404);
        $this->assertStringContainsString('<x-errors.page', $error500);
        $this->assertStringContainsString('<x-errors.page', $error503);
    }
}
