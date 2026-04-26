<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class InstallSuspiciousUserAgentContractTest extends TestCase
{
    public function test_install_paths_pass_user_agent_string_to_suspicious_user_agent_guard(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/InstallService.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/InstallController.php');

        $this->assertIsString($service);
        $this->assertIsString($controller);
        $this->assertStringContainsString('->isSuspicious((string) $request->userAgent())', $service);
        $this->assertStringContainsString('->isSuspicious((string) $request->userAgent())', $controller);
        $this->assertStringNotContainsString('->isSuspicious($request)', $service);
        $this->assertStringNotContainsString('->isSuspicious($request)', $controller);
    }
}
