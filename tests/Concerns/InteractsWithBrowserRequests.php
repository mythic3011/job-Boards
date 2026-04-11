<?php

namespace Tests\Concerns;

trait InteractsWithBrowserRequests
{
    protected function withBrowser(): static
    {
        return $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; TestBrowser/1.0)');
    }
}
