<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleSuspiciousUserAgent;
use App\Services\AuditLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class SuspiciousUserAgentContractTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function highRiskPathProvider(): array
    {
        return [
            'admin dashboard' => ['admin'],
            'nested admin page' => ['admin/users'],
            'installer' => ['install'],
            'installer status' => ['install/status'],
            'login' => ['login'],
        ];
    }

    #[DataProvider('highRiskPathProvider')]
    public function test_suspicious_ua_limiter_uses_strict_bucket_for_high_risk_paths(string $path): void
    {
        $limiter = RateLimiter::limiter('suspicious-ua');

        $this->assertIsCallable($limiter);

        /** @var Limit $limit */
        $limit = $limiter(Request::create('/' . ltrim($path, '/'), 'GET', server: [
            'REMOTE_ADDR' => '127.0.0.1',
        ]));

        $this->assertSame(5, $limit->maxAttempts);
        $this->assertSame(600, $limit->decaySeconds);
        $this->assertSame('127.0.0.1', $limit->key);
    }

    public function test_suspicious_user_agent_on_login_path_is_treated_as_high_risk_and_hidden(): void
    {
        $request = Request::create('/login', 'GET', server: [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 sqlmap scanner/1.0',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('logRequestEvent')
            ->once()
            ->with(
                'suspicious_user_agent',
                Mockery::type(Request::class),
                200,
                null,
                null,
                Mockery::on(fn (array $meta): bool => ($meta['matched_pattern'] ?? null) === 'sqlmap'
                    && ($meta['is_high_risk_path'] ?? false) === true)
            );
        $auditLogger->shouldReceive('logRequestEvent')
            ->once()
            ->with(
                'suspicious_ua_high_risk_path',
                Mockery::type(Request::class),
                404,
                null,
                null,
                Mockery::on(fn (array $meta): bool => ($meta['action'] ?? null) === 'returned_404')
            );

        $middleware = new HandleSuspiciousUserAgent($auditLogger);

        try {
            $middleware->handle($request, fn (): Response => response('ok'));
            $this->fail('Expected suspicious user agent on login path to be hidden with a 404 response.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }
}
