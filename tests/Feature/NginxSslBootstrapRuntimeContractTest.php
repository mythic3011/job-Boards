<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\Support\ObsTestFixtures;
use PHPUnit\Framework\TestCase;

final class NginxSslBootstrapRuntimeContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_prepare_repairs_legacy_rendered_ssl_include_directory_into_a_file(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installBootstrapFixture($tempRoot);
        $stateDir = $tempRoot.'/state';
        $renderedIncludePath = $stateDir.'/runtime/rendered/nginx.ssl-mode.conf';

        mkdir($renderedIncludePath, 0777, true);

        $process = new Process(
            [$scriptPath, 'prepare', 'self-signed'],
            $tempRoot,
            [
                'BT_STATE_DIR' => $stateDir,
                'BT_RUNTIME_DIR' => $stateDir.'/runtime',
                'BT_NGINX_CONTAINER_NAME' => 'jobs-boards-nginx-test',
                'PATH' => $tempRoot.'/bin:'.getenv('PATH'),
            ],
        );
        $process->setTimeout(120);
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertFileExists($renderedIncludePath);
        $this->assertFalse(is_dir($renderedIncludePath), 'Rendered SSL include path should be normalized back into a file.');

        $contents = file_get_contents($renderedIncludePath);
        $this->assertIsString($contents);
        $this->assertStringContainsString('/etc/nginx/ssl/selfsigned.crt', $contents);
        $this->assertStringContainsString('/etc/nginx/ssl/selfsigned.key', $contents);
    }

    public function test_prepare_self_signed_normalizes_ip_and_dns_alt_names_into_certificate_sans(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->installBootstrapFixture($tempRoot);
        $stateDir = $tempRoot.'/state';
        $certPath = $stateDir.'/runtime/nginx-ssl/selfsigned.crt';

        $process = new Process(
            [$scriptPath, 'prepare', 'self-signed'],
            $tempRoot,
            [
                'BT_STATE_DIR' => $stateDir,
                'BT_RUNTIME_DIR' => $stateDir.'/runtime',
                'BT_NGINX_CONTAINER_NAME' => 'jobs-boards-nginx-test',
                'SSL_CERT_DOMAIN' => '158.132.209.234',
                'SSL_SELF_SIGNED_ALT_NAMES' => '192.168.153.100,100.73.136.3,jobboard.local',
                'PATH' => $tempRoot.'/bin:'.getenv('PATH'),
            ],
        );
        $process->setTimeout(120);
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertFileExists($certPath);

        $certDump = new Process(['openssl', 'x509', '-noout', '-text', '-in', $certPath]);
        $certDump->setTimeout(60);
        $certDump->run();

        $this->assertSame(0, $certDump->getExitCode(), $certDump->getOutput().$certDump->getErrorOutput());
        $this->assertStringContainsString('IP Address:158.132.209.234', $certDump->getOutput());
        $this->assertStringContainsString('IP Address:192.168.153.100', $certDump->getOutput());
        $this->assertStringContainsString('IP Address:100.73.136.3', $certDump->getOutput());
        $this->assertStringContainsString('DNS:jobboard.local', $certDump->getOutput());
        $this->assertStringNotContainsString('DNS:158.132.209.234', $certDump->getOutput());
    }

    private function installBootstrapFixture(string $tempRoot): string
    {
        $scriptPath = $tempRoot.'/ops/bootstrap/bootstrap-nginx-ssl.sh';
        $templatePath = $tempRoot.'/docker/nginx/templates/ssl-mode.conf.tpl';

        ObsTestFixtures::installCommonLibFixture($this->repoRoot, $tempRoot);

        if (! is_dir(dirname($scriptPath))) {
            mkdir(dirname($scriptPath), 0777, true);
        }

        if (! is_dir(dirname($templatePath))) {
            mkdir(dirname($templatePath), 0777, true);
        }

        copy($this->repoRoot.'/ops/bootstrap/bootstrap-nginx-ssl.sh', $scriptPath);
        copy($this->repoRoot.'/docker/nginx/templates/ssl-mode.conf.tpl', $templatePath);
        chmod($scriptPath, 0755);

        $this->writeExecutable($tempRoot.'/bin/docker', <<<'BASH'
#!/usr/bin/env bash
exit 1
BASH
        );

        $this->writeExecutable($tempRoot.'/bin/crontab', <<<'BASH'
#!/usr/bin/env bash
if [ "${1:-}" = "-l" ]; then
    exit 0
fi

cat >/dev/null || true
exit 0
BASH
        );

        return $scriptPath;
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-nginx-ssl-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
    }

    private function writeExecutable(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $contents);
        chmod($path, 0755);
    }
}
