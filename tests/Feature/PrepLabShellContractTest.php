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

    public function test_prep_lab_resolves_hostname_from_explicit_or_detected_wan_contract(): void
    {
        $repoRoot = dirname(__DIR__, 2);
        $contents = file_get_contents($repoRoot.'/prep-lab.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('LAB_HOSTNAME="${LAB_HOSTNAME:-}"', $contents);
        $this->assertStringContainsString('LAB_PUBLIC_HOST="${LAB_PUBLIC_HOST:-}"', $contents);
        $this->assertStringContainsString('resolve_lab_hostname()', $contents);
        $this->assertStringContainsString('if [[ -n "${LAB_PUBLIC_HOST}" ]]', $contents);
        $this->assertStringContainsString('detected="$(detect_primary_ip)"', $contents);
        $this->assertStringContainsString('CANONICAL_APP_URL="https://${LAB_HOSTNAME}"', $contents);
        $this->assertStringContainsString('bt_upsert_env_file_value .env SSL_CERT_DOMAIN "${LAB_HOSTNAME}"', $contents);
        $this->assertStringContainsString('bt_upsert_env_file_value .env SSL_SELF_SIGNED_ALT_NAMES "${SSL_ALT_NAMES}"', $contents);
    }
}
