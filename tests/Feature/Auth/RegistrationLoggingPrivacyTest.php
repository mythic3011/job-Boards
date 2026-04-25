<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Services\UserRegistrationService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Psr\Log\AbstractLogger;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class RegistrationLoggingPrivacyTest extends TestCase
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

        Role::query()->create([
            'name' => 'individual',
            'guard_name' => 'web',
        ]);
    }

    public function test_successful_registration_log_does_not_contain_raw_identifiers_or_form_payload(): void
    {
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

        $this->withBrowser()
            ->post(route('register.store'), $this->honeypotFormPayload([
                'login_id' => 'privacy-user',
                'nickname' => 'Privacy User',
                'email' => 'privacy@example.test',
                'user_type' => 'individual',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
            ]))
            ->assertRedirect(route('home'));

        $successRecord = collect($logger->records)
            ->firstWhere('message', 'User registered successfully');

        $this->assertNotNull($successRecord);
        $this->assertArrayHasKey('user_id', $successRecord['context']);
        $this->assertSame('individual', $successRecord['context']['user_type'] ?? null);
        $this->assertArrayNotHasKey('username', $successRecord['context']);
        $this->assertArrayNotHasKey('email', $successRecord['context']);

        $encodedLogs = json_encode($logger->records);

        $this->assertIsString($encodedLogs);
        $this->assertStringNotContainsString('privacy-user', $encodedLogs);
        $this->assertStringNotContainsString('Privacy User', $encodedLogs);
        $this->assertStringNotContainsString('privacy@example.test', $encodedLogs);
        $this->assertStringNotContainsString('StrongPass123!', $encodedLogs);
        $this->assertStringNotContainsString('password_confirmation', $encodedLogs);
    }

    public function test_failed_registration_log_uses_submitted_fields_without_raw_values(): void
    {
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

        $this->mock(UserRegistrationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('register')
                ->once()
                ->andThrow(new \RuntimeException('registration exploded for failure-user / failure@example.test'));
        });

        $this->from(route('register'))
            ->withBrowser()
            ->post(route('register.store'), $this->honeypotFormPayload([
                'login_id' => 'failure-user',
                'nickname' => 'Failure User',
                'email' => 'failure@example.test',
                'user_type' => 'individual',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
            ]))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['error']);

        $errorRecord = collect($logger->records)
            ->firstWhere('message', 'Registration failed');

        $this->assertNotNull($errorRecord);
        $this->assertArrayNotHasKey('input', $errorRecord['context']);
        $this->assertArrayNotHasKey('error', $errorRecord['context']);
        $this->assertSame(
            ['login_id', 'nickname', 'email', 'user_type'],
            $errorRecord['context']['submitted_fields'] ?? null,
        );
        $this->assertStringNotContainsString((string) config('honeypot.field_name', 'website'), json_encode($errorRecord['context']));
        $this->assertStringNotContainsString('_timing', json_encode($errorRecord['context']));

        $encodedLogs = json_encode($logger->records);

        $this->assertIsString($encodedLogs);
        $this->assertStringNotContainsString('failure-user', $encodedLogs);
        $this->assertStringNotContainsString('Failure User', $encodedLogs);
        $this->assertStringNotContainsString('failure@example.test', $encodedLogs);
        $this->assertStringNotContainsString('StrongPass123!', $encodedLogs);
        $this->assertStringNotContainsString('password_confirmation', $encodedLogs);
    }
}
