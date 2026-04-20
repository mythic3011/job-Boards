<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class HeadlessInstallCommandTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('password_changed_at')->nullable();
        });
        $this->createSettingsTable();
        $this->createAuditLogsTable();
        $this->createPermissionTables();

        $request = Request::create('/cli/install/headless', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);
        $this->app->instance('request', $request);
    }

    public function test_headless_install_completes_setup_once_and_outputs_bootstrap_2fa_material(): void
    {
        $this->artisan('install:headless', [
            '--admin-email' => 'admin@example.com',
            '--admin-password' => 'StrongPass123!',
            '--admin-name' => 'Root Admin',
            '--app-name' => 'Jobs Boards',
            '--app-url' => 'https://jb.mythic3011.com',
            '--timezone' => 'Asia/Hong_Kong',
        ])
            ->expectsOutputToContain('Headless installation completed.')
            ->expectsOutputToContain('Two-factor secret:')
            ->expectsOutputToContain('Recovery codes:')
            ->assertExitCode(CommandAlias::SUCCESS);

        $admin = User::query()->where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->user_type);
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertNotNull($admin->two_factor_confirmed_at);
        $this->assertTrue(Setting::isSetupCompleted());
        $this->assertSame('Jobs Boards', Setting::get('app_name'));
        $this->assertSame('https://jb.mythic3011.com', Setting::get('app_url'));
        $this->assertSame('Asia/Hong_Kong', Setting::get('timezone'));
        $this->assertSame(1, AuditLog::query()->where('event_type', 'setup.completed')->count());
    }

    public function test_headless_install_skips_when_setup_is_already_completed(): void
    {
        Setting::markSetupCompleted();

        $this->artisan('install:headless', [
            '--admin-email' => 'admin@example.com',
            '--admin-password' => 'StrongPass123!',
            '--admin-name' => 'Root Admin',
        ])
            ->expectsOutputToContain('Setup already completed; skipping headless install.')
            ->assertExitCode(CommandAlias::SUCCESS);

        $this->assertNull(User::query()->where('email', 'admin@example.com')->first());
        $this->assertSame(0, AuditLog::query()->where('event_type', 'setup.completed')->count());
    }

    public function test_headless_install_can_read_the_admin_password_from_an_environment_variable_and_suppress_secret_output(): void
    {
        putenv('HEADLESS_INSTALL_PASSWORD=StrongPass123!');
        $_ENV['HEADLESS_INSTALL_PASSWORD'] = 'StrongPass123!';
        $_SERVER['HEADLESS_INSTALL_PASSWORD'] = 'StrongPass123!';

        $exitCode = Artisan::call('install:headless', [
            '--admin-email' => 'admin@example.com',
            '--admin-password-env' => 'HEADLESS_INSTALL_PASSWORD',
            '--admin-name' => 'Root Admin',
            '--app-name' => 'Jobs Boards',
            '--app-url' => 'https://jb.mythic3011.com',
            '--timezone' => 'Asia/Hong_Kong',
            '--credential-output' => 'none',
        ]);

        $output = Artisan::output();
        $admin = User::query()->where('email', 'admin@example.com')->first();

        $this->assertSame(CommandAlias::SUCCESS, $exitCode);
        $this->assertIsString($output);
        $this->assertStringNotContainsString('Two-factor secret:', $output);
        $this->assertStringNotContainsString('Recovery codes:', $output);
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->user_type);
        $this->assertTrue(Setting::isSetupCompleted());
    }

    public function test_headless_install_can_read_the_admin_password_from_a_file(): void
    {
        $passwordFile = tempnam(sys_get_temp_dir(), 'headless-install-password-');
        $this->assertNotFalse($passwordFile);
        file_put_contents($passwordFile, "StrongPass123!\n");

        try {
            $exitCode = Artisan::call('install:headless', [
                '--admin-email' => 'file-admin@example.com',
                '--admin-password-file' => $passwordFile,
                '--admin-name' => 'File Admin',
                '--credential-output' => 'none',
            ]);
        } finally {
            @unlink($passwordFile);
        }

        $output = Artisan::output();
        $admin = User::query()->where('email', 'file-admin@example.com')->first();

        $this->assertSame(CommandAlias::SUCCESS, $exitCode);
        $this->assertIsString($output);
        $this->assertStringNotContainsString('Two-factor secret:', $output);
        $this->assertStringNotContainsString('Recovery codes:', $output);
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->user_type);
        $this->assertTrue(Setting::isSetupCompleted());
    }
}
