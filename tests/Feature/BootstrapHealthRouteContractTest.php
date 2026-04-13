<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class BootstrapHealthRouteContractTest extends TestCase
{
    public function test_fresh_http_kernel_bootstrap_can_serve_the_health_route(): void
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/up', 'GET');
$response = $kernel->handle($request);

fwrite(STDOUT, (string) $response->getStatusCode());
$kernel->terminate($request, $response);
PHP;

        $process = new Process(
            [PHP_BINARY, '-d', 'display_errors=1', '-r', $script],
            dirname(__DIR__, 2),
            ['APP_ENV' => 'testing'],
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertSame('200', trim($process->getOutput()), $combinedOutput);
    }
}
