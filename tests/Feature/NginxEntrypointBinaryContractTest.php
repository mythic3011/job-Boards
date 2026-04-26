<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class NginxEntrypointBinaryContractTest extends TestCase
{
    public function test_openresty_image_entrypoint_execs_openresty_binary(): void
    {
        $repoRoot = dirname(__DIR__, 2);

        $dockerfile = file_get_contents($repoRoot.'/docker/nginx/Dockerfile');
        $entrypoint = file_get_contents($repoRoot.'/docker/nginx/entrypoint.sh');

        $this->assertIsString($dockerfile);
        $this->assertIsString($entrypoint);
        $this->assertStringContainsString('FROM openresty/openresty:alpine', $dockerfile);
        $this->assertStringContainsString('OPENRESTY_BIN="/usr/local/openresty/bin/openresty"', $entrypoint);
        $this->assertStringContainsString('"${OPENRESTY_BIN}" -t -c /etc/nginx/nginx.conf', $entrypoint);
        $this->assertStringContainsString('"${OPENRESTY_BIN}" -s reload -c /etc/nginx/nginx.conf', $entrypoint);
        $this->assertStringContainsString('if [ -d "${SSL_MODE_CONF}" ]; then', $entrypoint);
        $this->assertStringContainsString('WARNING: ${SSL_MODE_CONF} is a directory; removing it so SSL mode config can be rendered.', $entrypoint);
        $this->assertStringContainsString('rmdir "${SSL_MODE_CONF}" 2>/dev/null || rm -rf "${SSL_MODE_CONF}"', $entrypoint);
        $this->assertStringContainsString("exec \"\${OPENRESTY_BIN}\" -g 'daemon off;' -c /etc/nginx/nginx.conf", $entrypoint);
        $this->assertStringNotContainsString("exec nginx -g 'daemon off;' -c /etc/nginx/nginx.conf", $entrypoint);
    }
}
