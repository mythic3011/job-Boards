<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class ProfileUpdateFlowTest extends TestCase
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

    public function test_authenticated_user_can_update_profile_without_generic_failure(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_' . Str::uuid(),
            'login_id' => 'member1',
            'nickname' => 'Old Name',
            'email' => 'old@example.com',
            'password' => Hash::make('StrongPass123!'),
            'user_type' => 'individual',
        ]);

        $this->actingAs($user)
            ->withBrowser()
            ->put(route('profile.update'), [
                'nickname' => 'New Name',
                'email' => 'new@example.com',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'Profile updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nickname' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }
}
