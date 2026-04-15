<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class LogoutAuditTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createAuditLogsTable();
        $this->createSettingsTable();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_logout_creates_a_canonical_audit_log_entry(): void
    {
        $user = User::factory()->create([
            'user_type' => 'individual',
        ]);

        $this->withBrowser()
            ->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'audit.auth.logout',
            'actor_user_id' => $user->id,
            'source' => 'laravel',
            'outcome' => 'logout',
            'target_type' => 'user',
            'target_idcode' => $user->idcode,
        ]);
    }
}
