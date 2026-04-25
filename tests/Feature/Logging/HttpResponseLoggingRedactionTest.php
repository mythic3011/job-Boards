<?php

namespace Tests\Feature\Logging;

use App\Http\Middleware\LogHttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class HttpResponseLoggingRedactionTest extends TestCase
{
    public function test_http_response_logging_redacts_sensitive_query_values_in_url_and_referer(): void
    {
        config()->set('http_logging.enabled', true);
        config()->set('http_logging.log_success', false);
        config()->set('http_logging.log_redirects', false);
        config()->set('http_logging.exclude_paths', []);

        $logger = new class extends AbstractLogger
        {
            /** @var array<int, array{level: string, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        Log::swap($logger);

        $request = Request::create(
            'https://jobs.local/install?token=raw-token&next=%2Fdashboard',
            'GET'
        );
        $request->headers->set('referer', 'https://jobs.local/login?code=otp-code&src=mail');
        $request->attributes->set('request_id', 'rid-test-1');

        $middleware = app(LogHttpResponse::class);
        $response = $middleware->handle($request, fn () => response('not found', Response::HTTP_NOT_FOUND));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertNotEmpty($logger->records);

        $record = $logger->records[0];
        $context = $record['context'];

        $this->assertIsArray($context);
        $url = (string) ($context['url'] ?? '');
        $referer = (string) ($context['referer'] ?? '');

        $this->assertStringStartsWith('https://jobs.local/install?', $url);
        $this->assertStringContainsString('token=%5BREDACTED%5D', $url);
        $this->assertStringContainsString('next=%2Fdashboard', $url);
        $this->assertStringStartsWith('https://jobs.local/login?', $referer);
        $this->assertStringContainsString('code=%5BREDACTED%5D', $referer);
        $this->assertStringContainsString('src=mail', $referer);

        $encoded = json_encode($context);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('raw-token', $encoded);
        $this->assertStringNotContainsString('otp-code', $encoded);
    }
}
