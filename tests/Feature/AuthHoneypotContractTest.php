<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AuthHoneypotContractTest extends TestCase
{
    public function test_honeypot_protected_auth_forms_render_the_shared_honeypot_component(): void
    {
        $login = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/login.blade.php');
        $register = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/register.blade.php');
        $forgotPassword = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/forgot-password.blade.php');
        $resetPassword = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/reset-password.blade.php');

        $this->assertIsString($login);
        $this->assertIsString($register);
        $this->assertIsString($forgotPassword);
        $this->assertIsString($resetPassword);

        $this->assertStringContainsString('<x-honeypot />', $login);
        $this->assertStringContainsString('<x-honeypot />', $register);
        $this->assertStringContainsString('<x-honeypot />', $forgotPassword);
        $this->assertStringContainsString('<x-honeypot />', $resetPassword);
    }

    public function test_shared_honeypot_component_uses_the_configured_field_name_contract(): void
    {
        $component = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/honeypot.blade.php');

        $this->assertIsString($component);
        $this->assertStringContainsString("config('honeypot.field_name', 'website')", $component);
    }
}
