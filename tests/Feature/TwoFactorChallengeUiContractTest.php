<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class TwoFactorChallengeUiContractTest extends TestCase
{
    public function test_two_factor_challenge_page_uses_theme_aware_security_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/two-factor-challenge.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-auth-emblem', $contents);
        $this->assertStringContainsString('theme-link', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('bg-blue-50', $contents);
        $this->assertStringNotContainsString('border-gray-300', $contents);
    }
}
