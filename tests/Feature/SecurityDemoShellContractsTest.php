<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class SecurityDemoShellContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_zap_demo_wrapper_uses_containerized_baseline_scan_and_labelled_output_contract(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/demo/run-zap-baseline.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Usage: ops/demo/run-zap-baseline.sh <label> <target-url> [output-root]', $contents);
        $this->assertStringContainsString('ghcr.io/zaproxy/zaproxy:stable', $contents);
        $this->assertStringContainsString('zap-baseline.py', $contents);
        $this->assertStringContainsString('report.html', $contents);
        $this->assertStringContainsString('report.json', $contents);
        $this->assertStringContainsString('report.md', $contents);
        $this->assertStringContainsString('docker run --rm', $contents);
        $this->assertStringContainsString('ZAP_TARGET_CONTAINER:-jobs-boards-nginx', $contents);
        $this->assertStringContainsString('--add-host', $contents);
        $this->assertStringContainsString('--network', $contents);
        $this->assertStringContainsString('ZAP_BASELINE_POLICY:-ops/demo/zap-baseline-policy.conf', $contents);
        $this->assertStringContainsString('-c zap-baseline-policy.conf', $contents);
    }

    public function test_zap_demo_policy_demotes_expected_informational_findings(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/demo/zap-baseline-policy.conf');

        $this->assertIsString($contents);
        $this->assertStringContainsString("10015\tINFO\t", $contents);
        $this->assertStringContainsString("10049\tINFO\t", $contents);
        $this->assertStringContainsString("10111\tINFO\t", $contents);
        $this->assertStringContainsString("10112\tINFO\t", $contents);
    }

    public function test_security_demo_evidence_collector_materializes_repeatable_target_artifacts_and_external_check_links(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/demo/collect-security-demo-evidence.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Usage: ops/demo/collect-security-demo-evidence.sh <label> <target-url> [output-root]', $contents);
        $this->assertStringContainsString('curl -kfsSIL', $contents);
        $this->assertStringContainsString('openssl s_client', $contents);
        $this->assertStringContainsString('manifest.json', $contents);
        $this->assertStringContainsString('checklist.md', $contents);
        $this->assertStringContainsString('ssllabs.com/ssltest', $contents);
        $this->assertStringContainsString('whynopadlock.com', $contents);
    }
}
