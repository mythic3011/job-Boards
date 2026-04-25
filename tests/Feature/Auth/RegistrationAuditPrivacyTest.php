<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class RegistrationAuditPrivacyTest extends TestCase
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

    public function test_registration_audit_metadata_does_not_store_raw_identifiers(): void
    {
        $this->withBrowser()
            ->post(route('register.store'), $this->honeypotFormPayload([
                'login_id' => 'audit-privacy-user',
                'nickname' => 'Audit Privacy User',
                'email' => 'audit-privacy@example.test',
                'user_type' => 'individual',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
            ]))
            ->assertRedirect(route('home'));

        $user = User::query()->where('login_id', 'audit-privacy-user')->firstOrFail();
        $auditLog = AuditLog::query()
            ->where('event_type', 'user_registered')
            ->latest('occurred_at')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($user->idcode, $auditLog->target_idcode);
        $this->assertSame('individual', $auditLog->meta['user_type'] ?? null);
        $this->assertSame('active', $auditLog->meta['registration_state'] ?? null);
        $this->assertFalse($auditLog->meta['two_factor_requested'] ?? true);
        $this->assertFalse($auditLog->meta['has_profile_image'] ?? true);
        $this->assertArrayNotHasKey('email', $auditLog->meta);
        $this->assertArrayNotHasKey('username', $auditLog->meta);
        $this->assertArrayNotHasKey('login_id', $auditLog->meta);
        $this->assertArrayNotHasKey('nickname', $auditLog->meta);

        $encodedMeta = json_encode($auditLog->meta);

        $this->assertIsString($encodedMeta);
        $this->assertStringNotContainsString('audit-privacy-user', $encodedMeta);
        $this->assertStringNotContainsString('Audit Privacy User', $encodedMeta);
        $this->assertStringNotContainsString('audit-privacy@example.test', $encodedMeta);
    }
}
