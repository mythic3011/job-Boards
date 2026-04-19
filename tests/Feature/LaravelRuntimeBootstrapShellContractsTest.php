<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class LaravelRuntimeBootstrapShellContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_prepare_laravel_runtime_creates_required_runtime_directories_for_a_clean_checkout(): void
    {
        $scriptPath = $this->repoRoot.'/docker/prepare-laravel-runtime';
        $tempRoot = $this->makeTempDir();

        $this->assertFileExists($scriptPath);

        $process = new Process([$scriptPath, $tempRoot], $tempRoot, null, null, 20);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());

        foreach ([
            'bootstrap/cache',
            'storage/logs',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/testing',
            'storage/framework/views',
            'storage/framework/views/livewire',
        ] as $directory) {
            $this->assertDirectoryExists($tempRoot.'/'.$directory, $directory);
        }
    }

    public function test_container_entrypoints_prepare_laravel_runtime_before_running_artisan(): void
    {
        $startContainer = file_get_contents($this->repoRoot.'/docker/start-container');
        $compose = file_get_contents($this->repoRoot.'/compose.yaml');
        $appCompose = file_get_contents($this->repoRoot.'/compose.app.yml');

        $this->assertIsString($startContainer);
        $this->assertIsString($compose);
        $this->assertIsString($appCompose);
        $this->assertStringContainsString('prepare-laravel-runtime', $startContainer);
        $this->assertStringContainsString('prepare-laravel-runtime', $compose);
        $this->assertStringContainsString('prepare-laravel-runtime', $appCompose);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-runtime-bootstrap-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
    }
}
