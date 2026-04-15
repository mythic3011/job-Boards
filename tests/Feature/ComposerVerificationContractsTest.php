<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class ComposerVerificationContractsTest extends TestCase
{
    public function test_full_default_composer_test_uses_phpunit_directly(): void
    {
        $composer = file_get_contents(dirname(__DIR__, 2).'/composer.json');

        $this->assertIsString($composer);
        $this->assertStringContainsString('"vendor/bin/phpunit"', $composer);
        $this->assertStringNotContainsString('"@php artisan test"', $composer);
    }
}
