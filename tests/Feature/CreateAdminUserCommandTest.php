<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class CreateAdminUserCommandTest extends TestCase
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
        $this->createPermissionTables();
    }

    public function test_admin_create_can_read_the_password_from_an_environment_variable(): void
    {
        putenv('ADMIN_CREATE_PASSWORD=StrongPass123!');
        $_ENV['ADMIN_CREATE_PASSWORD'] = 'StrongPass123!';
        $_SERVER['ADMIN_CREATE_PASSWORD'] = 'StrongPass123!';

        $this->artisan('admin:create', [
            '--email' => 'admin@example.com',
            '--name' => 'Root Admin',
            '--password-env' => 'ADMIN_CREATE_PASSWORD',
        ])
            ->expectsOutputToContain('Admin user created successfully!')
            ->expectsOutputToContain('Login ID:')
            ->assertExitCode(CommandAlias::SUCCESS);

        $admin = User::query()->where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->user_type);
        $this->assertTrue($admin->hasRole('admin'));
    }

    public function test_admin_create_fails_closed_when_the_requested_password_environment_variable_is_missing(): void
    {
        putenv('MISSING_ADMIN_PASSWORD');
        unset($_ENV['MISSING_ADMIN_PASSWORD'], $_SERVER['MISSING_ADMIN_PASSWORD']);

        $this->artisan('admin:create', [
            '--email' => 'admin@example.com',
            '--name' => 'Root Admin',
            '--password-env' => 'MISSING_ADMIN_PASSWORD',
        ])
            ->expectsOutputToContain('Password environment variable [MISSING_ADMIN_PASSWORD] is empty or missing.')
            ->assertExitCode(CommandAlias::FAILURE);

        $this->assertNull(User::query()->where('email', 'admin@example.com')->first());
    }

    public function test_admin_create_can_read_the_password_from_a_file(): void
    {
        $passwordFile = tempnam(sys_get_temp_dir(), 'admin-create-password-');
        $this->assertNotFalse($passwordFile);
        file_put_contents($passwordFile, "StrongPass123!\n");

        try {
            $this->artisan('admin:create', [
                '--email' => 'admin-file@example.com',
                '--name' => 'File Admin',
                '--password-file' => $passwordFile,
            ])
                ->expectsOutputToContain('Admin user created successfully!')
                ->assertExitCode(CommandAlias::SUCCESS);
        } finally {
            @unlink($passwordFile);
        }

        $admin = User::query()->where('email', 'admin-file@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->user_type);
        $this->assertTrue($admin->hasRole('admin'));
    }

    public function test_admin_create_rejects_passwords_that_do_not_match_canonical_policy(): void
    {
        $this->artisan('admin:create', [
            '--email' => 'weak-admin@example.com',
            '--name' => 'Weak Admin',
            '--password' => 'alllowercase123',
        ])
            ->expectsOutputToContain('format is invalid')
            ->assertExitCode(CommandAlias::FAILURE);

        $this->assertNull(User::query()->where('email', 'weak-admin@example.com')->first());
    }
}
