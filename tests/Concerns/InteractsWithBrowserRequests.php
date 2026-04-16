<?php

namespace Tests\Concerns;

trait InteractsWithBrowserRequests
{
    protected function withBrowser(): static
    {
        return $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; TestBrowser/1.0)');
    }

    protected function honeypotFormPayload(array $payload, int $secondsSinceRender = 10): array
    {
        $fieldName = (string) config('honeypot.field_name', 'website');

        return array_merge([
            $fieldName => '',
            '_timing' => encrypt(time() - $secondsSinceRender),
        ], $payload);
    }
}
