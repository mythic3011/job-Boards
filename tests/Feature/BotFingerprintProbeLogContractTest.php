<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class BotFingerprintProbeLogContractTest extends TestCase
{
    public function test_nginx_and_crowdsec_key_banned_page_remediation_off_an_explicit_probe_marker(): void
    {
        $nginx = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/nginx.conf');
        $scenario = file_get_contents(dirname(__DIR__, 2).'/docker/crowdsec/scenarios/banned-page-probe.yaml');

        $this->assertIsString($nginx);
        $this->assertIsString($scenario);

        $this->assertStringContainsString("log_format blue_team_fp_probe 'trap=bot_fingerprint probe=\$arg_probe signal=\$arg_signal method=\$request_method status=\$status '", $nginx);
        $this->assertStringContainsString('access_log /var/log/nginx/fp-trap.log blue_team_fp_probe;', $nginx);
        $this->assertStringNotContainsString('"POST /api/bot/fp-log" in evt.Line.Raw', $scenario);
        $this->assertStringContainsString('"trap=bot_fingerprint" in evt.Line.Raw', $scenario);
        $this->assertStringContainsString('"probe=banned_page" in evt.Line.Raw', $scenario);
        $this->assertStringContainsString('"method=POST" in evt.Line.Raw', $scenario);
        $this->assertStringContainsString('"status=204" in evt.Line.Raw', $scenario);
    }
}
