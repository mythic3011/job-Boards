<?php

namespace Tests\Unit\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\InstallService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\AbstractLogger;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class InstallServiceTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createAuditLogsTable();
        $this->createSettingsTable();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_complete_installation_logs_canonical_reason_for_setup_completed(): void
    {
        $request = Request::create('/install/complete', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);
        $this->app->instance('request', $request);

        Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder', '--force' => true]);

        $auditLogger = app(\App\Services\AuditLogger::class);
        $twoFactorService = app(\App\Services\TwoFactorService::class);
        $service = Mockery::mock(InstallService::class, [$auditLogger, $twoFactorService])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('createAdminUser')
            ->once()
            ->andReturn(new User);
        $service->shouldReceive('enableTwoFactor')
            ->once()
            ->andReturnNull();
        $service->shouldReceive('storeSystemConfig')
            ->once()
            ->andReturnNull();

        $service->completeInstallation([
            'admin_name' => 'Admin User',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'StrongPass123!',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'recovery_codes' => ['RCODE-1'],
            'install_demo_data' => false,
        ]);

        $log = AuditLog::query()->where('event_type', 'setup.completed')->first();

        $this->assertNotNull($log);
        $this->assertSame('setup', $log->target_idcode);
        $this->assertSame(['reason' => 'installation_complete'], $log->meta);
    }

    public function test_complete_installation_forces_demo_seeders_in_production_safe_path(): void
    {
        $request = Request::create('/install/complete', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);
        $this->app->instance('request', $request);

        Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder', '--force' => true]);
        Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder', '--force' => true]);

        $auditLogger = app(\App\Services\AuditLogger::class);
        $twoFactorService = app(\App\Services\TwoFactorService::class);
        $service = Mockery::mock(InstallService::class, [$auditLogger, $twoFactorService])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('createAdminUser')
            ->once()
            ->andReturn(new User);
        $service->shouldReceive('enableTwoFactor')
            ->once()
            ->andReturnNull();
        $service->shouldReceive('storeSystemConfig')
            ->once()
            ->andReturnNull();

        $service->completeInstallation([
            'admin_name' => 'Admin User',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'StrongPass123!',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'recovery_codes' => ['RCODE-1'],
            'install_demo_data' => true,
        ]);

        $this->assertSame(1, AuditLog::query()->where('event_type', 'setup.completed')->count());
        $this->assertSame(1, AuditLog::query()->where('event_type', 'setup.demo_data_seeded')->count());
    }

    public function test_complete_installation_failure_log_does_not_contain_raw_admin_email_or_trace(): void
    {
        $request = Request::create('/install/complete', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);
        $this->app->instance('request', $request);

        $logger = new class extends AbstractLogger
        {
            /** @var array<int, array{level: string, message: string, context: array}> */
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

        Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder', '--force' => true]);

        $auditLogger = app(\App\Services\AuditLogger::class);
        $twoFactorService = app(\App\Services\TwoFactorService::class);
        $service = Mockery::mock(InstallService::class, [$auditLogger, $twoFactorService])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('createAdminUser')
            ->once()
            ->andThrow(new \RuntimeException('install exploded for admin@example.com'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('install exploded for admin@example.com');

        try {
            $service->completeInstallation([
                'admin_name' => 'Admin User',
                'admin_email' => 'admin@example.com',
                'admin_password' => 'StrongPass123!',
                'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
                'recovery_codes' => ['RCODE-1'],
                'install_demo_data' => false,
            ]);
        } finally {
            $record = collect($logger->records)
                ->firstWhere('message', 'Installation failed');

            $this->assertNotNull($record);
            $this->assertArrayNotHasKey('error', $record['context']);
            $this->assertArrayNotHasKey('trace', $record['context']);
            $this->assertSame(\RuntimeException::class, $record['context']['error_class'] ?? null);

            $encodedLogs = json_encode($logger->records);

            $this->assertIsString($encodedLogs);
            $this->assertStringNotContainsString('admin@example.com', $encodedLogs);
        }
    }
}
