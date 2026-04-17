<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class BaseLayoutHeadContractTest extends TestCase
{
    public function test_base_layout_exposes_shared_head_metadata_contract(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/layouts/base.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("'description' => null", $contents);
        $this->assertStringContainsString('meta name="description"', $contents);
        $this->assertStringContainsString('meta name="application-name"', $contents);
        $this->assertStringContainsString('meta name="color-scheme" content="light dark"', $contents);
        $this->assertStringContainsString('meta name="theme-color" media="(prefers-color-scheme: light)"', $contents);
        $this->assertStringContainsString('meta name="theme-color" media="(prefers-color-scheme: dark)"', $contents);
        $this->assertStringContainsString("@stack('meta')", $contents);
        $this->assertStringContainsString("@stack('head')", $contents);
    }

    public function test_base_layout_places_vite_assets_in_head_and_provides_skip_link(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/layouts/base.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('<x-layouts.assets />', $contents);
        $this->assertStringContainsString('href="#main-content"', $contents);
        $this->assertStringContainsString('id="main-content"', $contents);
        $this->assertStringContainsString("'bodyClass' => ''", $contents);
        $this->assertStringContainsString("'mainClass' => ''", $contents);
    }
}
