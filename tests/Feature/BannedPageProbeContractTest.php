<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class BannedPageProbeContractTest extends TestCase
{
    public function test_banned_page_uses_a_single_explicit_probe_contract_for_load_and_interaction_beacons(): void
    {
        $html = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/errors/banned.html');
        $script = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/errors/banned-page.js');

        $this->assertIsString($html);
        $this->assertIsString($script);
        $this->assertStringContainsString('<script src="/_error/banned-page.js" defer></script>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('probe: "banned_page"', $script);
        $this->assertStringContainsString('URLSearchParams({ probe: "banned_page", signal })', $script);
        $this->assertStringContainsString('postProbe("page_load")', $script);
        $this->assertStringContainsString('postProbe("mousemove")', $script);
        $this->assertStringContainsString('navigator.sendBeacon(', $script);
        $this->assertStringNotContainsString('/secret-trap-', $script);
    }

    public function test_banned_page_static_decoy_links_match_the_explicit_nginx_honeypot_surface(): void
    {
        $html = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/errors/banned.html');

        $this->assertIsString($html);
        $this->assertStringContainsString('href="/.env"', $html);
        $this->assertStringContainsString('href="/.git/config"', $html);
        $this->assertStringContainsString('href="/phpmyadmin"', $html);
        $this->assertStringContainsString('href="/wp-login.php"', $html);
        $this->assertStringContainsString('href="/admin-old"', $html);
        $this->assertStringNotContainsString('href="/wp-admin"', $html);
        $this->assertStringNotContainsString('href="/api/v1/users"', $html);
        $this->assertStringNotContainsString('href="/admin/config/setup"', $html);
        $this->assertStringNotContainsString('href="/config.php"', $html);
        $this->assertStringNotContainsString('href="/backup.sql"', $html);
        $this->assertStringNotContainsString('href="/.env.production"', $html);
        $this->assertStringNotContainsString('href="/server-status"', $html);
        $this->assertStringNotContainsString('href="/actuator/health"', $html);
    }
}
