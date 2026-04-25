<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class EnvExampleContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_normal_env_example_excludes_dead_aws_placeholders(): void
    {
        $contents = file_get_contents($this->repoRoot.'/.env.example');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('AWS_ACCESS_KEY_ID=', $contents);
        $this->assertStringNotContainsString('AWS_SECRET_ACCESS_KEY=', $contents);
        $this->assertStringNotContainsString('AWS_DEFAULT_REGION=', $contents);
        $this->assertStringNotContainsString('AWS_BUCKET=', $contents);
        $this->assertStringNotContainsString('AWS_USE_PATH_STYLE_ENDPOINT=', $contents);
    }

    public function test_normal_env_example_keeps_single_monitoring_plaintext_source(): void
    {
        $contents = file_get_contents($this->repoRoot.'/.env.example');

        $this->assertIsString($contents);
        $this->assertStringContainsString('MONITORING_PASSWORD=', $contents);
        $this->assertStringNotContainsString("\nGRAFANA_PASSWORD=", $contents);
        $this->assertStringNotContainsString("\nPROMETHEUS_PASSWORD=", $contents);
    }
}

