<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InstallCompletionCoordinator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command as CommandAlias;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {--email= : Admin email address}
                            {--password= : Admin password}
                            {--password-env= : Environment variable containing the admin password}
                            {--password-file= : File path containing the admin password; use php://stdin for piped input}
                            {--name= : Admin name}';

    protected $description = 'Create the first admin user (fallback for headless deployments)';

    public function __construct(
        private readonly InstallCompletionCoordinator $installCompletionCoordinator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $passwordEnv = (string) ($this->option('password-env') ?? '');
        $passwordFile = trim((string) ($this->option('password-file') ?? ''));
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->option('password');
        if ($passwordEnv !== '') {
            $password = env($passwordEnv) ?: $_ENV[$passwordEnv] ?? $_SERVER[$passwordEnv] ?? getenv($passwordEnv) ?: null;
            if (! is_string($password) || $password === '') {
                $this->error("Password environment variable [{$passwordEnv}] is empty or missing.");

                return CommandAlias::FAILURE;
            }
        } elseif ($passwordFile !== '') {
            $password = $this->readPasswordFromFile($passwordFile);
            if ($password === null) {
                return CommandAlias::FAILURE;
            }
        }
        $password = $password ?: $this->secret('Password');
        $name = $this->option('name') ?: $this->ask('Name/Nickname', 'Admin User');

        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
            'name' => $name,
        ], [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => $this->installCompletionCoordinator->passwordRules(),
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return CommandAlias::FAILURE;
        }

        // Get or create admin role
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        // Generate unique login_id
        $loginId = 'admin_' . Str::random(8);

        $user = User::create([
            'idcode' => 'user_admin_' . Str::uuid()->toString(),
            'login_id' => $loginId,
            'nickname' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'user_type' => 'admin',
        ]);

        $user->assignRole($adminRole);

        $this->info("Admin user created successfully!");
        $this->info("Email: {$email}");
        $this->info("Login ID: {$loginId}");
        $this->warn("Please enable 2FA for this admin account!");

        return CommandAlias::SUCCESS;
    }

    private function readPasswordFromFile(string $passwordFile): ?string
    {
        $password = @file_get_contents($passwordFile);
        if ($password === false) {
            $this->error("Password file [{$passwordFile}] is unreadable.");

            return null;
        }

        $password = rtrim($password, "\r\n");
        if ($password === '') {
            $this->error("Password file [{$passwordFile}] is empty.");

            return null;
        }

        return $password;
    }
}
