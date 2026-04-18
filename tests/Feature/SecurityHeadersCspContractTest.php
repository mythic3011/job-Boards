<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersCspContractTest extends TestCase
{
    public function test_security_headers_keep_script_policy_nonce_based_without_unsafe_eval(): void
    {
        $middleware = new SecurityHeaders();
        $request = Request::create('/profile/edit', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok'));
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertIsString($csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertMatchesRegularExpression("/script-src[^;]*'nonce-[^']+'/", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-inline'/", $csp);
    }

    public function test_base_layout_uses_external_theme_bootstrap_instead_of_inline_script(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/layouts/base.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("src=\"{{ asset('js/theme-bootstrap.js') }}\"", $contents);
        $this->assertStringNotContainsString('<script nonce="{{ csp_nonce() }}">', $contents);
    }
}
