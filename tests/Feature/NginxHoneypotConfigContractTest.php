<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class NginxHoneypotConfigContractTest extends TestCase
{
    public function test_nginx_defines_blue_team_trap_name_before_honeypot_log_format(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/nginx.conf');

        $this->assertIsString($contents);

        $this->assertStringContainsString('map $uri $blue_team_trap_name {', $contents);
        $this->assertStringContainsString('default "-";', $contents);
        $this->assertStringContainsString('/.env env_probe;', $contents);
        $this->assertStringContainsString('/.git/config git_probe;', $contents);
        $this->assertStringContainsString('/phpmyadmin phpmyadmin_probe;', $contents);
        $this->assertStringContainsString('/wp-login.php wp_probe;', $contents);
        $this->assertStringContainsString('/admin-old admin_old_probe;', $contents);

        $mapOffset = strpos($contents, 'map $uri $blue_team_trap_name {');
        $logFormatOffset = strpos($contents, "log_format blue_team_honeypot 'trap=web_decoy trap_name=\$blue_team_trap_name '");

        $this->assertNotFalse($mapOffset);
        $this->assertNotFalse($logFormatOffset);
        $this->assertLessThan($logFormatOffset, $mapOffset);
    }
}
