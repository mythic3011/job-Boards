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
        $this->assertStringContainsString('data-admin-nav-trigger-summary', $navigation);
        $this->assertStringNotContainsString('aria-label="Admin navigation"', $navigation);
        $this->assertStringContainsString('Signed in as', $header);
        $this->assertStringContainsString('data-profile-dropdown-panel', $header);
        $this->assertStringContainsString('Workspace', $header);
        $this->assertStringContainsString('Security', $header);
        $this->assertStringContainsString('Session', $header);
        $this->assertStringContainsString('max-h-[min(75vh,32rem)]', $header);
        $this->assertStringContainsString('overflow-y-auto', $header);
    }

    public function test_profile_dropdown_uses_theme_aware_icon_tiles_and_status_tokens(): void
    {
        $header = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/layouts/header.blade.php');
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        $this->assertIsString($header);
        $this->assertIsString($css);
        $this->assertStringContainsString('$adminDestinations = auth()->user()->isAdmin()', $header);
        $this->assertStringContainsString('$primaryAdminDestination = $adminDestinations[0] ?? null;', $header);
        $this->assertStringContainsString('$dashboardHref = auth()->user()->isAdmin()', $header);
        $this->assertStringContainsString('($primaryAdminDestination[\'href\'] ?? route(\'home\'))', $header);
        $this->assertStringContainsString('href="{{ $dashboardHref }}"', $header);
        $this->assertStringContainsString('Open Admin Workspace', $header);
        $this->assertStringContainsString('theme-dropdown-item', $header);
        $this->assertStringContainsString('theme-dropdown-item-active', $header);
        $this->assertStringContainsString('aria-current="{{', $header);
        $this->assertStringContainsString('theme-icon-tile', $header);
        $this->assertStringContainsString('theme-alert-success', $header);
        $this->assertStringContainsString('theme-alert-warning', $header);
        $this->assertStringNotContainsString("route('admin.dashboard')", $header);
        $this->assertStringContainsString('.theme-dropdown-item {', $css);
        $this->assertStringContainsString('.theme-dropdown-item-active {', $css);
        $this->assertStringContainsString('.theme-dropdown-item-active .theme-text-muted', $css);
        $this->assertStringNotContainsString('border-gray-200', $header);
        $this->assertStringNotContainsString('bg-gray-100', $header);
        $this->assertStringNotContainsString('bg-yellow-50', $header);
        $this->assertStringNotContainsString('bg-green-50', $header);
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
