<?php

namespace App\Services;

class HttpLogRedactionService
{
    public function redactUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        $query = '';
        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $params);
            foreach ($params as $key => $value) {
                if ($this->isSensitiveKey((string) $key)) {
                    $params[$key] = '[REDACTED]';
                }
            }

            $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        return $this->buildUrl($parts, $query);
    }

    private function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match('/(token|code|session|secret|password|recovery|signature|sig|auth|key)/i', $key);
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function buildUrl(array $parts, string $query): string
    {
        $scheme = isset($parts['scheme']) ? "{$parts['scheme']}://" : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ":{$parts['pass']}" : '';
        $auth = $user !== '' ? "{$user}{$pass}@" : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';
        $path = $parts['path'] ?? '';
        $fragment = isset($parts['fragment']) ? "#{$parts['fragment']}" : '';
        $queryPart = $query !== '' ? "?{$query}" : '';

        return "{$scheme}{$auth}{$host}{$port}{$path}{$queryPart}{$fragment}";
    }
}
