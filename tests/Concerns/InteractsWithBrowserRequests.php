<?php

namespace Tests\Concerns;

trait InteractsWithBrowserRequests
{
    protected function withBrowser(): static
    {
        return $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; TestBrowser/1.0)');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function honeypotFormPayload(array $payload = [], int $renderedSecondsAgo = 10): array
    {
        return [
            'website' => '',
            '_timing' => encrypt(time() - $renderedSecondsAgo),
            ...$payload,
        ];
    }
}
