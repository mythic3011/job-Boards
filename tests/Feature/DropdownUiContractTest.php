<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class DropdownUiContractTest extends TestCase
{
    public function test_navigation_and_profile_dropdowns_use_the_refined_panel_contract(): void
    {
        $navigation = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/layouts/navigation.blade.php');
        $header = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/layouts/header.blade.php');

        $this->assertIsString($navigation);
        $this->assertIsString($header);

        $this->assertStringContainsString('data-dropdown-panel', $navigation);
        $this->assertStringContainsString('data-dropdown-panel', $header);
        $this->assertStringContainsString('Admin tools', $navigation);
        $this->assertStringContainsString('Signed in as', $header);
    }

    public function test_dropdown_javascript_coordinates_open_state_across_menus(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/dropdown.js');

        $this->assertIsString($contents);
        $this->assertStringContainsString('closeAllDropdowns', $contents);
        $this->assertStringContainsString('dataset.open', $contents);
        $this->assertStringContainsString('aria-expanded', $contents);
        $this->assertStringContainsString('Escape', $contents);
    }
}
