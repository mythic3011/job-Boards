<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;
use Spatie\Permission\Models\Role;

class InstallService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Check if installation is already completed.
     */
    public function isInstallationCompleted(): bool
    {
        try {
            if (!Schema::hasTable('settings')) {
                return false;
            }

            return Setting::isSetupCompleted();
        } catch (Exception $e) {
            Log::debug('Error checking installation status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Security: Check if installation is allowed from current context
     */
    public function isInstallationAllowed(Request $request): array
    {
        $issues = [];

        // Check if already installed
        if ($this->isInstallationCompleted()) {
            $issues[] = 'Installation already completed';
        }

        // Check HTTPS (except localhost)
        if (!$request->secure() &&
            $request->getHost() !== 'localhost' &&
            !str_starts_with($request->getHost(), '127.')) {
            $issues[] = 'HTTPS required for installation';
        }

        // Check for suspicious user agent
        if (app(\App\Http\Middleware\HandleSuspiciousUserAgent::class)->isSuspicious($request)) {
            $issues[] = 'Suspicious request detected';
        }

        // Check rate limiting
        $key = 'install_attempts_' . $request->ip();
        $attempts = Cache::get($key, 0);
        if ($attempts >= 5) {
            $issues[] = 'Too many installation attempts';
        }

        return [
            'allowed' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Run system checks.
     */
    public function runSystemChecks(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
        ];
    }

    /**
     * Check database connection.
     */
    protected function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (Exception $e) {
            Log::debug('Database check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check storage is writable.
     */
    protected function checkStorage(): bool
    {
        try {
            return Storage::disk('local')->put('test.txt', 'test')
                && Storage::disk('local')->delete('test.txt');
        } catch (Exception $e) {
            Log::debug('Storage check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check cache driver.
     */
    protected function checkCache(): bool
    {
        try {
            cache()->put('test', 'test', 1);
            $result = cache()->get('test') === 'test';
            cache()->forget('test');
            return $result;
        } catch (Exception $e) {
            Log::debug('Cache check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Complete the installation process.
     */
    public function completeInstallation(array $data): void
    {
        DB::beginTransaction();

        try {
            // Ensure roles and permissions exist
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);

            // Create admin user
            $admin = $this->createAdminUser($data);
            $this->enableTwoFactor($admin, $data['two_factor_secret'] ?? null, $data['recovery_codes'] ?? []);

            // Store system configuration
            $this->storeSystemConfig($data);

            // Install demo data if requested
            if ($data['install_demo_data'] ?? false) {
                $this->installDemoData();
            }

            // Mark setup as completed
            Setting::markSetupCompleted();

            // Log installation completion
            $this->auditLogger->logBusinessEvent(
                eventType: 'setup.completed',
                request: request(),
                targetType: 'system',
                targetIdcode: 'setup',
                meta: ['reason' => 'installation_complete'],
            );

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Installation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Store system configuration settings.
     */
    protected function storeSystemConfig(array $data): void
    {
        if (isset($data['app_name'])) {
            Setting::set('app_name', $data['app_name']);
        }

        if (isset($data['app_url'])) {
            Setting::set('app_url', $data['app_url']);
        }

        if (isset($data['timezone'])) {
            Setting::set('timezone', $data['timezone']);
        }
    }

    /**
     * Create the admin user.
     */
    protected function createAdminUser(array $data): User
    {
        $adminRole = Role::where('name', 'admin')->first();

        if (!$adminRole) {
            throw new Exception('Admin role not found. Please run RolePermissionSeeder first.');
        }

        $admin = User::create([
            'idcode' => 'user_admin_' . Str::uuid()->toString(),
            'login_id' => 'admin_' . Str::random(8),
            'nickname' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => Hash::make($data['admin_password']),
            'user_type' => 'admin',
            'password_changed_at' => now(),
        ]);

        $admin->assignRole($adminRole);

        return $admin;
    }

    /**
     * Enable 2FA for the admin created during install using provided Base32 secret.
     */
    protected function enableTwoFactor(User $user, ?string $base32Secret, array $recoveryCodes = []): void
    {
        if (!$base32Secret) {
            return;
        }

        $secret = trim($base32Secret);
        $secretLength = (int) config('fortify-options.two-factor-authentication.secret-length', 16);
        if (strlen($secret) < $secretLength) {
            // basic guard against malformed secret
            return;
        }

        // Use provided recovery codes or generate new ones
        if (empty($recoveryCodes)) {
            $recoveryCodes = collect(range(1, 8))->map(fn () => RecoveryCode::generate())->all();
        }

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        TwoFactorAuthenticationEnabled::dispatch($user);
    }

    /**
     * Install demo data.
     */
    protected function installDemoData(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);

        $this->auditLogger->logBusinessEvent(
            eventType: 'setup.demo_data_seeded',
            request: request(),
            targetType: 'system',
            meta: [
                'seeded_at' => now()->toDateTimeString(),
            ]
        );
    }
}
