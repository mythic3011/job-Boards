<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class ThemeUiContractTest extends TestCase
{
    public function test_base_layout_bootstraps_centralized_theme_state(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/layouts/base.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-theme-root', $contents);
        $this->assertStringContainsString('data-theme-preference', $contents);
        $this->assertStringContainsString('data-theme-mode', $contents);
        $this->assertStringContainsString('data-theme-accent', $contents);
        $this->assertStringContainsString("asset('js/theme-bootstrap.js')", $contents);
    }

    public function test_base_layout_includes_a_global_back_to_top_control(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/layouts/base.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-floating-controls', $contents);
        $this->assertStringContainsString('theme-floating-stack', $contents);
        $this->assertStringContainsString('data-back-to-top', $contents);
        $this->assertStringContainsString('aria-label="Back to top"', $contents);
        $this->assertStringContainsString('aria-controls="main-content"', $contents);
        $this->assertStringContainsString('theme-floating-action', $contents);
        $this->assertStringContainsString('Back to top', $contents);
    }

    public function test_theme_switcher_surfaces_appearance_and_palette_controls(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/theme-switcher.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-theme-switcher', $contents);
        $this->assertStringContainsString('Appearance', $contents);
        $this->assertStringContainsString('System', $contents);
        $this->assertStringContainsString('Light', $contents);
        $this->assertStringContainsString('Dark', $contents);
        $this->assertStringContainsString('Graphite', $contents);
        $this->assertStringContainsString('Forest', $contents);
    }

    public function test_theme_switcher_uses_a_viewport_safe_panel_and_compact_palette_cards(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/theme-switcher.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-theme-switcher-panel', $contents);
        $this->assertStringContainsString('max-h-[min(75vh,34rem)]', $contents);
        $this->assertStringContainsString('overflow-y-auto', $contents);
        $this->assertStringContainsString('data-theme-palette-grid', $contents);
        $this->assertStringContainsString('data-theme-palette-card', $contents);
    }

    public function test_theme_switcher_trigger_prefers_icon_first_copy_with_accessible_label(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/theme-switcher.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('aria-label="Open appearance controls"', $contents);
        $this->assertStringContainsString('class="sr-only">Appearance</span>', $contents);
        $this->assertStringNotContainsString("hidden lg:inline'>Appearance", $contents);
    }

    public function test_theme_javascript_handles_system_mode_and_palette_preferences(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/theme.js');

        $this->assertIsString($contents);
        $this->assertStringContainsString('jobs-board.theme.preference', $contents);
        $this->assertStringContainsString('jobs-board.theme.accent', $contents);
        $this->assertStringContainsString('prefers-color-scheme: dark', $contents);
        $this->assertStringContainsString('data-theme-preference-option', $contents);
        $this->assertStringContainsString('data-theme-accent-option', $contents);
        $this->assertStringContainsString('theme:changed', $contents);
    }

    public function test_app_javascript_bootstraps_the_back_to_top_controller(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/js/app.js');

        $this->assertIsString($contents);
        $this->assertStringContainsString('./components/back-to-top', $contents);
    }

    public function test_back_to_top_javascript_tracks_scroll_state_and_requests_smooth_scroll(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/back-to-top.js');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-back-to-top', $contents);
        $this->assertStringContainsString('data-visible', $contents);
        $this->assertStringContainsString('window.scrollTo', $contents);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $contents);
        $this->assertStringContainsString('behavior: prefersReducedMotion ? "auto" : "smooth"', $contents);
        $this->assertStringContainsString('requestAnimationFrame', $contents);
    }

    public function test_stylesheet_defines_light_dark_and_palette_theme_tokens(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        $this->assertIsString($contents);
        $this->assertStringContainsString('--app-page-bg', $contents);
        $this->assertStringContainsString('html[data-theme-mode="dark"]', $contents);
        $this->assertStringContainsString('html[data-theme-accent="graphite"]', $contents);
        $this->assertStringContainsString('html[data-theme-accent="forest"]', $contents);
        $this->assertStringContainsString('.theme-switcher-option[data-active="true"]', $contents);
        $this->assertStringContainsString('.theme-floating-stack', $contents);
        $this->assertStringContainsString('.theme-floating-action', $contents);
    }

    public function test_dark_graphite_and_forest_themes_promote_readable_link_contrast(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        $this->assertIsString($contents);
        $this->assertStringContainsString('--app-link-accent', $contents);
        $this->assertStringContainsString('--app-link-accent: #e2e8f0;', $contents);
        $this->assertStringContainsString('--app-link-accent: #99f6e4;', $contents);
    }
}
