<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\AuditLog;
use App\Services\InstallService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Mockery;
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
        $service = Mockery::mock(InstallService::class, [$auditLogger])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('createAdminUser')
            ->once()
            ->andReturn(new User());
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
        $service = Mockery::mock(InstallService::class, [$auditLogger])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('createAdminUser')
            ->once()
            ->andReturn(new User());
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
}
