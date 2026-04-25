<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Psr\Log\AbstractLogger;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class AuthenticationLoggingPrivacyTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createSettingsTable();
        $this->createUsersTable();
        $this->createPermissionTables();
        $this->createAuditLogsTable();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Setting::setBool('setup_completed', true);
    }

    public function test_successful_login_log_does_not_contain_raw_identifier_or_email(): void
    {
        $logger = $this->makeLogger();
        Log::swap($logger);

        $user = $this->createUser([
            'login_id' => 'privacy-login',
            'email' => 'privacy-login@example.test',
        ]);

        $this->withBrowser()
            ->post(route('login.store'), $this->honeypotFormPayload([
                'login_id' => $user->login_id,
                'password' => 'StrongPass123!',
            ]))
            ->assertRedirect(route('my.applications.index'));

        $record = collect($logger->records)
            ->firstWhere('message', 'User authenticated successfully');

        $this->assertNotNull($record);
        $this->assertSame($user->id, $record['context']['user_id'] ?? null);
        $this->assertArrayNotHasKey('username', $record['context']);
        $this->assertArrayNotHasKey('email', $record['context']);

        $encodedLogs = json_encode($logger->records);

        $this->assertIsString($encodedLogs);
        $this->assertStringNotContainsString('privacy-login', $encodedLogs);
        $this->assertStringNotContainsString('privacy-login@example.test', $encodedLogs);
    }

    public function test_failed_login_log_does_not_contain_raw_submitted_identifier(): void
    {
        $logger = $this->makeLogger();
        Log::swap($logger);

        $this->from(route('login'))
            ->withBrowser()
            ->post(route('login.store'), $this->honeypotFormPayload([
                'login_id' => ' MissingUser@Example.com ',
                'password' => 'WrongPass123!',
            ]))
            ->assertRedirect(route('login'));

        $record = collect($logger->records)
            ->firstWhere('message', 'Authentication failed');

        $this->assertNotNull($record);
        $this->assertSame('user_not_found', $record['context']['reason'] ?? null);
        $this->assertArrayNotHasKey('username', $record['context']);
        $this->assertNotNull($record['context']['target_idcode'] ?? null);

        $encodedLogs = json_encode($logger->records);

        $this->assertIsString($encodedLogs);
        $this->assertStringNotContainsString('MissingUser@Example.com', $encodedLogs);
        $this->assertStringNotContainsString('missinguser@example.com', $encodedLogs);
    }

    public function test_lockout_logs_do_not_contain_raw_identifier_or_email(): void
    {
        $logger = $this->makeLogger();
        Log::swap($logger);

        Config::set('auth.max_login_attempts', 3);
        Config::set('auth.lockout_minutes', 30);

        $user = $this->createUser([
            'login_id' => 'privacy-lockout',
            'email' => 'privacy-lockout@example.test',
        ]);

        foreach (range(1, 4) as $attempt) {
            $this->from(route('login'))
                ->withBrowser()
                ->post(route('login.store'), $this->honeypotFormPayload([
                    'login_id' => $user->login_id,
                    'password' => 'WrongPass123!',
                ]))
                ->assertRedirect(route('login'));
        }

        $lockRecord = collect($logger->records)
            ->firstWhere('message', 'Account locked due to failed attempts');
        $lockedAttemptRecord = collect($logger->records)
            ->firstWhere('message', 'Login attempt on locked account');

        $this->assertNotNull($lockRecord);
        $this->assertSame($user->id, $lockRecord['context']['user_id'] ?? null);
        $this->assertArrayNotHasKey('username', $lockRecord['context']);

        $this->assertNotNull($lockedAttemptRecord);
        $this->assertSame($user->id, $lockedAttemptRecord['context']['user_id'] ?? null);
        $this->assertArrayNotHasKey('username', $lockedAttemptRecord['context']);

        $encodedLogs = json_encode($logger->records);

        $this->assertIsString($encodedLogs);
        $this->assertStringNotContainsString('privacy-lockout', $encodedLogs);
        $this->assertStringNotContainsString('privacy-lockout@example.test', $encodedLogs);
    }

    private function createUser(array $attributes): User
    {
        return User::factory()->create([
            'password' => Hash::make('StrongPass123!'),
            'user_type' => 'individual',
            ...$attributes,
        ]);
    }

    private function makeLogger(): object
    {
        return new class extends AbstractLogger
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
    }
}
