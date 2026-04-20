<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class PrepLabShellContractTest extends TestCase
{
    public function test_prep_lab_quotes_app_name_for_valid_dotenv_output(): void
    {
        $repoRoot = dirname(__DIR__, 2);
        $contents = file_get_contents($repoRoot.'/prep-lab.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('bt_upsert_env_file_value .env APP_NAME \'"Jobs Boards"\'', $contents);
    }
}
