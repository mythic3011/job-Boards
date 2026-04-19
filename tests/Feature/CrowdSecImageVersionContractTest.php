<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class CrowdSecImageVersionContractTest extends TestCase
{
    public function test_compose_files_pin_supported_crowdsec_engine_tag(): void
    {
        $repoRoot = dirname(__DIR__, 2);
        $expectedImage = 'crowdsecurity/crowdsec:v1.7.7';

        foreach (['compose.yaml', 'compose.app.yml'] as $composeFile) {
            $contents = file_get_contents($repoRoot.'/'.$composeFile);

            $this->assertIsString($contents);
            $this->assertStringContainsString("image: {$expectedImage}", $contents);
            $this->assertStringNotContainsString('image: crowdsecurity/crowdsec:v1.6.8', $contents);
            $this->assertStringNotContainsString('image: crowdsecurity/crowdsec:latest', $contents);
        }
    }
}
