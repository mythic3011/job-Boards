<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Services\InstallService;
use App\Services\TwoFactorService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

class HeadlessInstall extends Command
{
    protected $signature = 'install:headless
                            {--admin-email= : Admin email address}
                            {--admin-password= : Admin password}
                            {--admin-password-env= : Environment variable containing the admin password}
                            {--admin-password-file= : File path containing the admin password; use php://stdin for piped input}
                            {--admin-name=Admin User : Admin display name}
                            {--app-name= : Application name}
                            {--app-url= : Public application URL}
                            {--timezone= : Application timezone}
                            {--two-factor-secret= : Optional pre-generated Base32 TOTP secret}
                            {--credential-output=stdout : How to emit bootstrap secrets [stdout|none|json]}
                            {--install-demo-data : Seed demo data during install}';

    protected $description = 'Complete the first-install flow without the browser installer';

    public function __construct(
        private readonly InstallService $installService,
        private readonly TwoFactorService $twoFactorService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (Setting::isSetupCompleted()) {
            $this->line('Setup already completed; skipping headless install.');

            return CommandAlias::SUCCESS;
        }

        $credentialOutput = (string) ($this->option('credential-output') ?: 'stdout');
        if (! in_array($credentialOutput, ['stdout', 'none', 'json'], true)) {
            $this->error('credential-output must be one of: stdout, none, json');

            return CommandAlias::INVALID;
        }

        $password = (string) ($this->option('admin-password') ?? '');
        $passwordEnv = trim((string) ($this->option('admin-password-env') ?? ''));
        $passwordFile = trim((string) ($this->option('admin-password-file') ?? ''));
        if ($passwordEnv !== '') {
            $password = env($passwordEnv) ?: $_ENV[$passwordEnv] ?? $_SERVER[$passwordEnv] ?? getenv($passwordEnv) ?: '';
            if ($password === '') {
                $this->error("Admin password environment variable [{$passwordEnv}] is empty or missing.");

                return CommandAlias::FAILURE;
            }
        } elseif ($passwordFile !== '') {
            $password = $this->readPasswordFromFile($passwordFile);
            if ($password === null) {
                return CommandAlias::FAILURE;
            }
        }

        $data = [
            'admin_email' => (string) $this->option('admin-email'),
            'admin_password' => $password,
            'admin_name' => (string) $this->option('admin-name'),
            'app_name' => $this->option('app-name'),
            'app_url' => $this->option('app-url'),
            'timezone' => $this->option('timezone'),
            'install_demo_data' => (bool) $this->option('install-demo-data'),
        ];

        $validator = Validator::make($data, [
            'admin_email' => ['required', 'email'],
            'admin_password' => ['required', 'string', 'min:12'],
            'admin_name' => ['required', 'string', 'max:255'],
            'app_name' => ['nullable', 'string', 'max:255'],
            'app_url' => ['nullable', 'url', 'max:255'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return CommandAlias::INVALID;
        }

        $twoFactorSecret = trim((string) ($this->option('two-factor-secret') ?: ''));
        if ($twoFactorSecret === '') {
            $twoFactorSecret = $this->twoFactorService->generateSetupSecret();
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes(8);

        $request = Request::create('/cli/install/headless', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'artisan install:headless',
        ]);
        $request->attributes->set('request_id', (string) Str::uuid());
        $this->laravel->instance('request', $request);

        $this->installService->completeInstallation([
            'admin_name' => $data['admin_name'],
            'admin_email' => $data['admin_email'],
            'admin_password' => $data['admin_password'],
            'two_factor_secret' => $twoFactorSecret,
            'recovery_codes' => $recoveryCodes,
            'app_name' => $data['app_name'],
            'app_url' => $data['app_url'],
            'timezone' => $data['timezone'],
            'install_demo_data' => $data['install_demo_data'],
        ]);

        /** @var User|null $admin */
        $admin = User::query()->where('email', $data['admin_email'])->first();

        if ($credentialOutput === 'json') {
            $this->line(json_encode([
                'admin_login_id' => $admin?->login_id,
                'two_factor_secret' => $twoFactorSecret,
                'recovery_codes' => $recoveryCodes,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return CommandAlias::SUCCESS;
        }

        $this->info('Headless installation completed.');
        if ($admin !== null) {
            $this->line('Admin login ID: '.$admin->login_id);
        }
        if ($credentialOutput === 'stdout') {
            $this->line('Two-factor secret: '.$twoFactorSecret);
            $this->line('Recovery codes:');
            foreach ($recoveryCodes as $code) {
                $this->line(' - '.$code);
            }
        }

        return CommandAlias::SUCCESS;
    }

    private function readPasswordFromFile(string $passwordFile): ?string
    {
        $password = @file_get_contents($passwordFile);
        if ($password === false) {
            $this->error("Admin password file [{$passwordFile}] is unreadable.");

            return null;
        }

        $password = rtrim($password, "\r\n");
        if ($password === '') {
            $this->error("Admin password file [{$passwordFile}] is empty.");

            return null;
        }

        return $password;
    }
}
