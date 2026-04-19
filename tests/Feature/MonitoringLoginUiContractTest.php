<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class MonitoringLoginUiContractTest extends TestCase
{
    public function test_monitoring_login_frontend_reuses_the_php_auth_theme_surface(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/docker/auth-service/frontend/src/main.jsx');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-auth-shell', $contents);
        $this->assertStringContainsString('theme-auth-emblem', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-panel', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('Workspace Access', $contents);
        $this->assertStringContainsString('Security Notes', $contents);
        $this->assertStringNotContainsString('bg-brand-surface', $contents);
        $this->assertStringNotContainsString('text-white', $contents);
    }

    public function test_monitoring_login_stylesheet_defines_shared_auth_theme_tokens_and_controls(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/docker/auth-service/frontend/src/index.css');

        $this->assertIsString($contents);
        $this->assertStringContainsString('--app-page-bg:', $contents);
        $this->assertStringContainsString('.theme-page-shell {', $contents);
        $this->assertStringContainsString('.theme-panel {', $contents);
        $this->assertStringContainsString('.theme-panel-subtle {', $contents);
        $this->assertStringContainsString('.theme-auth-shell {', $contents);
        $this->assertStringContainsString('.theme-auth-emblem {', $contents);
        $this->assertStringContainsString('.theme-button-primary {', $contents);
        $this->assertStringContainsString('.theme-input {', $contents);
    }
}
