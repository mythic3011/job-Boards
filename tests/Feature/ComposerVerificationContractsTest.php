<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Verification contract: composer verification entrypoints and worktree guard docs.
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

    public function test_worktree_verification_rejects_symlinked_vendor_and_requires_local_vendor_directory(): void
    {
        $composer = file_get_contents(dirname(__DIR__, 2).'/composer.json');
        $docs = file_get_contents(dirname(__DIR__, 2).'/docs/runbooks/test-verification-paths.md');
        $readme = file_get_contents(dirname(__DIR__, 2).'/README.md');

        $this->assertIsString($composer);
        $this->assertIsString($docs);
        $this->assertIsString($readme);
        $this->assertStringContainsString('"test:worktree"', $composer);
        $this->assertStringContainsString('Symlinked vendor/ points at another checkout and breaks autoload resolution.', $composer);
        $this->assertStringContainsString('three intentional verification entrypoints', $docs);
        $this->assertStringContainsString('`vendor/bin/phpunit` (or any command using that `vendor/autoload.php`)', $docs);
        $this->assertStringContainsString('composer test:worktree', $docs);
        $this->assertStringContainsString('three intentional test entrypoints', $readme);
        $this->assertStringContainsString('rejects symlinked or missing `vendor/`', $readme);
        $this->assertStringContainsString('do not symlink `vendor/` from another checkout', $readme);
    }
}
