<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class SecurityHeadersCspContractTest extends TestCase
{
    use UsesInMemorySqlite;

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
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
    }

    public function test_security_headers_avoid_wildcard_source_directives_and_set_cross_origin_isolation_policy(): void
    {
        $middleware = new SecurityHeaders();
        $request = Request::create('/install', 'GET', [], [], [], ['HTTPS' => 'on']);

        $response = $middleware->handle($request, fn () => new Response('ok'));
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertIsString($csp);
        $this->assertStringNotContainsString('https:', $csp);
        $this->assertStringNotContainsString('http:', $csp);
        $this->assertStringNotContainsString('*', $csp);
        $this->assertSame('require-corp', $response->headers->get('Cross-Origin-Embedder-Policy'));
        $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Opener-Policy'));
        $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Resource-Policy'));
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_security_headers_set_explicit_no_store_cache_contract_for_dynamic_web_responses(): void
    {
        $middleware = new SecurityHeaders();
        $request = Request::create('/login', 'GET', [], [], [], ['HTTPS' => 'on']);

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame('max-age=0, must-revalidate, no-cache, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('0', $response->headers->get('Expires'));
    }

    public function test_web_responses_do_not_emit_javascript_readable_xsrf_cookie(): void
    {
        $this->useInMemorySqlite();
        $this->createSettingsTable();

        Route::middleware('web')->get('/_test/security/cookie-contract', fn () => response('ok'));

        $response = $this->get('/_test/security/cookie-contract');

        $response->assertOk();
        $this->assertStringNotContainsString('XSRF-TOKEN=', implode("\n", $response->headers->all('Set-Cookie')));
    }

    public function test_base_layout_uses_external_theme_bootstrap_instead_of_inline_script(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/layouts/base.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("src=\"{{ asset('js/theme-bootstrap.js') }}\"", $contents);
        $this->assertStringNotContainsString('<script nonce="{{ csp_nonce() }}">', $contents);
    }
}
