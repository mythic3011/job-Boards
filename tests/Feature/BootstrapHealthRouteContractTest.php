<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class BootstrapHealthRouteContractTest extends TestCase
{
    public function test_fresh_http_kernel_bootstrap_can_serve_the_health_route(): void
    {
        $environment = [
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        ];

        $script = <<<'PHP'
$autoloadCandidates = [
    getcwd().'/vendor/autoload.php',
    dirname(getcwd(), 2).'/vendor/autoload.php',
];

$autoloadPath = null;

foreach ($autoloadCandidates as $candidate) {
    if (is_file($candidate)) {
        $autoloadPath = $candidate;
        break;
    }
}

if ($autoloadPath === null) {
    fwrite(STDERR, 'Unable to resolve vendor/autoload.php for bootstrap subprocess.'.PHP_EOL);
    exit(1);
}

$loader = require $autoloadPath;

if ($loader instanceof Composer\Autoload\ClassLoader) {
    $loader->setPsr4('App\\', [getcwd().'/app']);
    $loader->setPsr4('Database\\Factories\\', [getcwd().'/database/factories']);
    $loader->setPsr4('Database\\Seeders\\', [getcwd().'/database/seeders']);
    $loader->setPsr4('Tests\\', [getcwd().'/tests']);
}

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
            $environment,
            null,
            20,
        );
        $process->run();

        $combinedOutput = $process->getOutput().$process->getErrorOutput();

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertSame('200', trim($process->getOutput()), $combinedOutput);
    }
}
