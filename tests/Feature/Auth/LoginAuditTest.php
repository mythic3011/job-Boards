<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class LoginAuditTest extends TestCase
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

    public function test_successful_login_uses_canonical_verify_success_event(): void
    {
        $user = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'member1',
            'email' => 'member1@example.com',
        ]);

        $this->withBrowser()
            ->post(route('login.store'), $this->honeypotFormPayload([
                'login_id' => $user->login_id,
                'password' => 'StrongPass123!',
            ]))
            ->assertRedirect(route('my.applications.index'));

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'audit.auth.verify.success',
            'actor_user_id' => $user->id,
            'source' => 'laravel',
            'outcome' => 'success',
            'target_type' => 'user',
            'target_idcode' => $user->idcode,
            'status_code' => 200,
        ]);

        $log = \App\Models\AuditLog::query()
            ->where('event_type', 'audit.auth.verify.success')
            ->latest('occurred_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($user->login_id, $log->meta['username'] ?? null);
        $this->assertArrayNotHasKey('email', $log->meta);
        $this->assertSame(
            1,
            \App\Models\AuditLog::query()->where('event_type', 'audit.auth.verify.success')->count(),
        );
    }

    public function test_failed_login_uses_canonical_verify_denied_event(): void
    {
        $user = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'member2',
            'email' => 'member2@example.com',
        ]);

        $this->from(route('login'))
            ->withBrowser()
            ->post(route('login.store'), $this->honeypotFormPayload([
                'login_id' => $user->login_id,
                'password' => 'WrongPass123!',
            ]))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'audit.auth.verify.denied',
            'source' => 'laravel',
            'outcome' => 'denied',
            'target_type' => 'user',
            'target_idcode' => $user->idcode,
            'status_code' => 422,
        ]);
    }

    public function test_unknown_user_failed_login_still_uses_non_null_canonical_target(): void
    {
        $this->from(route('login'))
            ->withBrowser()
            ->post(route('login.store'), $this->honeypotFormPayload([
                'login_id' => ' MissingUser@Example.com ',
                'password' => 'WrongPass123!',
            ]))
            ->assertRedirect(route('login'));

        $log = \App\Models\AuditLog::query()
            ->where('event_type', 'audit.auth.verify.denied')
            ->latest('occurred_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('denied', $log->outcome);
        $this->assertSame('guest', $log->actor_type);
        $this->assertSame(422, $log->status_code);
        $this->assertSame('login_identifier', $log->target_type);
        $this->assertSame(
            'login_'.hash('sha256', 'missinguser@example.com'),
            $log->target_idcode,
        );
        $this->assertSame('user_not_found', $log->meta['reason'] ?? null);
        $this->assertSame('missinguser@example.com', $log->meta['username'] ?? null);
    }

    public function test_lockout_threshold_creates_canonical_locked_event(): void
    {
        Config::set('auth.max_login_attempts', 3);
        Config::set('auth.lockout_minutes', 30);

        $user = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'member3',
            'email' => 'member3@example.com',
        ]);

        foreach (range(1, 3) as $attempt) {
            $this->from(route('login'))
                ->withBrowser()
                ->post(route('login.store'), $this->honeypotFormPayload([
                    'login_id' => $user->login_id,
                    'password' => 'WrongPass123!',
                ]))
                ->assertRedirect(route('login'));
        }

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'audit.auth.locked',
            'source' => 'laravel',
            'outcome' => 'denied',
            'target_type' => 'user',
            'target_idcode' => $user->idcode,
            'status_code' => 422,
        ]);
    }

    public function test_locked_account_attempt_uses_canonical_verify_denied_reason(): void
    {
        $user = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'member4',
            'email' => 'member4@example.com',
            'locked_until' => now()->addMinutes(30),
        ]);

        $this->from(route('login'))
            ->withBrowser()
            ->post(route('login.store'), $this->honeypotFormPayload([
                'login_id' => $user->login_id,
                'password' => 'StrongPass123!',
            ]))
            ->assertRedirect(route('login'));

        $log = \App\Models\AuditLog::query()
            ->where('target_idcode', $user->idcode)
            ->latest('occurred_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('audit.auth.verify.denied', $log->event_type);
        $this->assertSame('denied', $log->outcome);
        $this->assertSame(422, $log->status_code);
        $this->assertSame('account_locked', $log->meta['reason'] ?? null);
        $this->assertArrayNotHasKey('locked_until', $log->meta);
    }

    private function createUser(array $attributes): User
    {
        return User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'idcode' => 'user_' . \Illuminate\Support\Str::uuid(),
            'nickname' => 'Test User',
            'password' => Hash::make('StrongPass123!'),
            ...$attributes,
        ]);
    }
}
