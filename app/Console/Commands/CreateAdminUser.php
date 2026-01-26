<?php

namespace App\Console\Commands;

use App\Models\User;
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
                            {--name= : Admin name}';

    protected $description = 'Create the first admin user (fallback for headless deployments)';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->option('password') ?: $this->secret('Password');
        $name = $this->option('name') ?: $this->ask('Name/Nickname', 'Admin User');

        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
            'name' => $name,
        ], [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
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
            'user_type' => 'company', // Admin uses company type for now
        ]);

        $user->assignRole($adminRole);

        $this->info("Admin user created successfully!");
        $this->info("Email: {$email}");
        $this->info("Login ID: {$loginId}");
        $this->warn("Please enable 2FA for this admin account!");

        return CommandAlias::SUCCESS;
    }
}
