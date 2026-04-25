<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Psr\Log\AbstractLogger;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class PasswordResetLoggingPrivacyTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createSettingsTable();
        $this->createUsersTable();
        $this->createAuditLogsTable();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Setting::setBool('setup_completed', true);
    }

    public function test_password_reset_completion_log_does_not_contain_raw_email(): void
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

        $user = User::factory()->create([
            'email' => 'reset-privacy@example.test',
        ]);

        Password::shouldReceive('reset')
            ->once()
            ->andReturnUsing(function (array $credentials, callable $callback) use ($user) {
                $callback($user, $credentials['password']);

                return Password::PASSWORD_RESET;
            });

        $this->withBrowser()
            ->post(route('password.update'), $this->honeypotFormPayload([
                'token' => 'reset-token',
                'email' => $user->email,
                'password' => 'An0therStrong!Pass123',
                'password_confirmation' => 'An0therStrong!Pass123',
            ]))
            ->assertRedirect(route('login'));

        $record = collect($logger->records)
            ->firstWhere('message', 'Password reset completed');

        $this->assertNotNull($record);
        $this->assertSame($user->id, $record['context']['user_id'] ?? null);
        $this->assertArrayNotHasKey('email', $record['context']);

        $encodedLogs = json_encode($logger->records);

        $this->assertIsString($encodedLogs);
        $this->assertStringNotContainsString('reset-privacy@example.test', $encodedLogs);
    }
}
