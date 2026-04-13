<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class NginxErrorPageContractTest extends TestCase
{
    public function test_nginx_routes_403_429_and_50x_through_static_error_pages(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/nginx.conf');

        $this->assertIsString($contents);
        $this->assertStringContainsString('error_page 403 /403.html;', $contents);
        $this->assertStringContainsString('location = /403.html {', $contents);
        $this->assertStringContainsString('error_page 429 /429.html;', $contents);
        $this->assertStringContainsString('error_page 500 502 503 504 520 521 522 523 524 /50x.html;', $contents);
        $this->assertStringContainsString('location = /_error/styles.css {', $contents);
        $this->assertStringContainsString('alias /usr/share/nginx/html/styles.css;', $contents);
        $this->assertStringContainsString('location = /_error/banned-page.js {', $contents);
        $this->assertStringContainsString('alias /usr/share/nginx/html/banned-page.js;', $contents);
    }

    public function test_static_nginx_error_pages_share_the_themed_stylesheet_contract(): void
    {
        $error403 = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/errors/403.html');
        $error429 = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/errors/429.html');
        $error50x = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/errors/50x.html');
        $banned = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/errors/banned.html');
        $monitoringLogin = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/errors/monitoring-login.html');
        $styles = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/errors/styles.css');

        $this->assertIsString($error403);
        $this->assertIsString($error429);
        $this->assertIsString($error50x);
        $this->assertIsString($banned);
        $this->assertIsString($monitoringLogin);
        $this->assertIsString($styles);

        $this->assertStringContainsString('href="/_error/styles.css"', $error403);
        $this->assertStringContainsString('href="/_error/styles.css"', $error429);
        $this->assertStringContainsString('href="/_error/styles.css"', $error50x);
        $this->assertStringContainsString('href="/_error/styles.css"', $banned);
        $this->assertStringContainsString('href="/_error/styles.css"', $monitoringLogin);
        $this->assertStringNotContainsString('<style>', $error429);
        $this->assertStringNotContainsString('<style>', $error50x);
        $this->assertStringContainsString('--error-page-bg:', $styles);
        $this->assertStringContainsString('.surface {', $styles);
        $this->assertStringContainsString('.action {', $styles);
        $this->assertStringContainsString('.pill {', $styles);
    }
}
