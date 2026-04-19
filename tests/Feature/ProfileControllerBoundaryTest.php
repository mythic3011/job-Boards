<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireTwoFactorEnabled;
use App\Models\User;
use App\Services\ProfileService;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class ProfileControllerBoundaryTest extends TestCase
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
        $this->withoutMiddleware(RequireTwoFactorEnabled::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_profile_update_only_forwards_allowlisted_fields_to_the_service(): void
    {
        $user = $this->createUser();

        $profileService = Mockery::mock(ProfileService::class);
        $profileService->shouldReceive('updateProfile')
            ->once()
            ->withArgs(function (User $candidate, array $data, Request $request) use ($user): bool {
                return $candidate->is($user)
                    && $data === [
                        'nickname' => 'New Name',
                        'email' => 'new@example.test',
                    ]
                    && $request->route()?->getName() === 'profile.update';
            })
            ->andReturn($user);

        $twoFactorService = Mockery::mock(TwoFactorService::class);

        $this->app->instance(ProfileService::class, $profileService);
        $this->app->instance(TwoFactorService::class, $twoFactorService);

        $this->actingAs($user)
            ->withBrowser()
            ->put(route('profile.update'), [
                '_token' => 'csrf-token',
                'nickname' => 'New Name',
                'email' => 'new@example.test',
                'locked_until' => now()->addDay()->toDateTimeString(),
                'two_factor_secret' => 'sneaky-secret',
                'unexpected' => 'should-not-pass',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'Profile updated successfully.');
    }

    public function test_password_update_only_forwards_allowlisted_fields_to_the_service(): void
    {
        $user = $this->createUser();

        $profileService = Mockery::mock(ProfileService::class);
        $profileService->shouldReceive('updatePassword')
            ->once()
            ->withArgs(function (User $candidate, array $data, Request $request) use ($user): bool {
                return $candidate->is($user)
                    && $data === [
                        'current_password' => 'StrongPass123!',
                        'password' => 'NewStrongPass123!',
                        'password_confirmation' => 'NewStrongPass123!',
                        'two_factor_code' => '123456',
                    ]
                    && $request->route()?->getName() === 'profile.password.update';
            });

        $twoFactorService = Mockery::mock(TwoFactorService::class);

        $this->app->instance(ProfileService::class, $profileService);
        $this->app->instance(TwoFactorService::class, $twoFactorService);

        $this->actingAs($user)
            ->withBrowser()
            ->put(route('profile.password.update'), [
                '_token' => 'csrf-token',
                'current_password' => 'StrongPass123!',
                'password' => 'NewStrongPass123!',
                'password_confirmation' => 'NewStrongPass123!',
                'two_factor_code' => '123456',
                'locked_until' => now()->addDay()->toDateTimeString(),
                'two_factor_secret' => 'sneaky-secret',
                'unexpected' => 'should-not-pass',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'Password updated successfully.');
    }

    public function test_profile_update_does_not_mutate_sensitive_user_state_from_submitted_fields(): void
    {
        $user = $this->createUser();

        $twoFactorService = Mockery::mock(TwoFactorService::class);
        $this->app->instance(TwoFactorService::class, $twoFactorService);

        $this->actingAs($user)
            ->withBrowser()
            ->put(route('profile.update'), [
                'nickname' => 'Boundary Safe',
                'email' => 'safe@example.test',
                'locked_until' => now()->addDay()->toDateTimeString(),
                'two_factor_secret' => encrypt('secret'),
                'two_factor_recovery_codes' => encrypt(json_encode(['CODE-ONE'])),
                'two_factor_confirmed_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'Profile updated successfully.');

        $user->refresh();

        $this->assertSame('Boundary Safe', $user->nickname);
        $this->assertSame('safe@example.test', $user->email);
        $this->assertNull($user->locked_until);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    private function createUser(): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_' . Str::uuid(),
            'login_id' => 'user_' . Str::lower(Str::random(6)),
            'nickname' => 'Profile User',
            'email' => Str::lower(Str::random(8)) . '@example.test',
            'password' => Hash::make('StrongPass123!'),
            'user_type' => 'individual',
        ]);
    }
}
