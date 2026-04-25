<?php

namespace Tests\Feature\Profile;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\AbstractLogger;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class ProfileUpdateLoggingTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createSettingsTable();
        $this->createAuditLogsTable();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_profile_update_does_not_log_raw_email_or_nickname(): void
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

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withBrowser()
            ->put(route('profile.update'), [
                'nickname' => 'Newnick',
                'email' => 'new@example.com',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'Profile updated successfully.');

        $encodedLogs = json_encode($logger->records);

        $this->assertIsString($encodedLogs);
        $this->assertStringNotContainsString('new@example.com', $encodedLogs);
        $this->assertStringNotContainsString('Newnick', $encodedLogs);
    }

    public function test_profile_update_audit_metadata_redacts_sensitive_profile_fields(): void
    {
        $user = User::factory()->create([
            'nickname' => 'Oldnick',
            'email' => 'old@example.com',
        ]);

        $this->actingAs($user)
            ->withBrowser()
            ->put(route('profile.update'), [
                'nickname' => 'Newnick',
                'email' => 'new@example.com',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'Profile updated successfully.');

        $auditLog = AuditLog::query()
            ->where('event_type', 'profile_updated')
            ->latest('occurred_at')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($user->id, $auditLog->meta['user_id'] ?? null);
        $this->assertSame(
            ['changed' => true, 'sensitive' => true],
            $auditLog->meta['changes']['nickname'] ?? null,
        );
        $this->assertSame(
            ['changed' => true, 'sensitive' => true],
            $auditLog->meta['changes']['email'] ?? null,
        );

        $encodedMeta = json_encode($auditLog->meta);

        $this->assertIsString($encodedMeta);
        $this->assertStringNotContainsString('old@example.com', $encodedMeta);
        $this->assertStringNotContainsString('new@example.com', $encodedMeta);
        $this->assertStringNotContainsString('Oldnick', $encodedMeta);
        $this->assertStringNotContainsString('Newnick', $encodedMeta);
    }

    public function test_profile_update_failure_log_does_not_contain_raw_exception_message_or_trace(): void
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

        $user = User::factory()->create();

        $profileService = Mockery::mock(ProfileService::class);
        $profileService->shouldReceive('updateProfile')
            ->once()
            ->andThrow(new \RuntimeException('profile exploded for new@example.com with avatar raw-file-name.png'));
        $this->app->instance(ProfileService::class, $profileService);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->withBrowser()
            ->put(route('profile.update'), [
                'nickname' => 'Newnick',
                'email' => 'new@example.com',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors(['error']);

        $record = collect($logger->records)
            ->firstWhere('message', 'Profile update failed');

        $this->assertNotNull($record);
        $this->assertArrayNotHasKey('error', $record['context']);
        $this->assertArrayNotHasKey('trace', $record['context']);
        $this->assertSame($user->id, $record['context']['user_id'] ?? null);
        $this->assertSame(['nickname', 'email'], $record['context']['submitted_fields'] ?? null);

        $encodedLogs = json_encode($logger->records);

        $this->assertIsString($encodedLogs);
        $this->assertStringNotContainsString('new@example.com', $encodedLogs);
        $this->assertStringNotContainsString('raw-file-name.png', $encodedLogs);
    }
}
