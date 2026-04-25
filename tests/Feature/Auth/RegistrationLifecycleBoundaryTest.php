<?php

namespace Tests\Feature\Auth;

use App\Livewire\Profile\TwoFactor;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class RegistrationLifecycleBoundaryTest extends TestCase
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
        $this->createJobPostingsTable();
        $this->createApplicationsTable();
        $this->createAuditLogsTable();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Setting::setBool('setup_completed', true);
    }

    public function test_registration_with_two_factor_marks_user_pending_and_redirects_to_setup(): void
    {
        $this->withBrowser()
            ->post(route('register.store'), $this->honeypotFormPayload([
                'login_id' => 'pendinguser',
                'nickname' => 'Pending User',
                'email' => 'pending@example.test',
                'user_type' => 'individual',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'enable_2fa' => '1',
            ]))
            ->assertRedirect(route('profile.two-factor'));

        $user = User::query()->where('login_id', 'pendinguser')->first();

        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('pending_2fa', $user->registration_state);
        $this->assertNotNull($user->two_factor_secret);
    }

    public function test_registration_without_two_factor_is_active_immediately(): void
    {
        $this->withBrowser()
            ->post(route('register.store'), $this->honeypotFormPayload([
                'login_id' => 'activeuser',
                'nickname' => 'Active User',
                'email' => 'active@example.test',
                'user_type' => 'individual',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
            ]))
            ->assertRedirect(route('home'));

        $user = User::query()->where('login_id', 'activeuser')->first();

        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('active', $user->registration_state);
    }

    public function test_pending_user_is_redirected_from_home_to_two_factor_setup(): void
    {
        $user = $this->makePendingUser();

        $this->actingAs($user)
            ->withBrowser()
            ->get(route('home'))
            ->assertRedirect(route('profile.two-factor'));
    }

    public function test_pending_user_is_redirected_from_profile_show_to_two_factor_setup(): void
    {
        $user = $this->makePendingUser();

        $this->actingAs($user)
            ->withBrowser()
            ->get(route('profile.show'))
            ->assertRedirect(route('profile.two-factor'));

        $this->assertSame(route('profile.show'), session('registration.pending_intended'));
    }

    public function test_pending_user_is_redirected_from_fortify_profile_information_update_to_two_factor_setup(): void
    {
        $user = $this->makePendingUser();

        $this->actingAs($user)
            ->withBrowser()
            ->put(route('user-profile-information.update'), [
                'nickname' => 'Blocked Update',
                'email' => 'blocked-update@example.test',
            ])
            ->assertRedirect(route('profile.two-factor'));
    }

    public function test_pending_user_is_redirected_from_password_confirmation_to_two_factor_setup(): void
    {
        $user = $this->makePendingUser();

        $this->actingAs($user)
            ->withBrowser()
            ->get(route('password.confirm'))
            ->assertRedirect(route('profile.two-factor'));
    }

    public function test_pending_user_is_redirected_from_profile_image_route_to_two_factor_setup(): void
    {
        $user = $this->makePendingUser();

        $this->actingAs($user)
            ->withBrowser()
            ->get(route('images.profile', ['path' => 'abc123']))
            ->assertRedirect(route('profile.two-factor'));
    }

    public function test_pending_user_can_still_logout(): void
    {
        $user = $this->makePendingUser();

        $this->actingAs($user)
            ->withBrowser()
            ->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'You have been successfully logged out.');

        $this->assertGuest();
    }

    public function test_successful_two_factor_confirmation_promotes_pending_user_to_active(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->individual()->create([
            'email' => 'pending-confirm@example.test',
            'nickname' => 'Pending Confirm',
        ]);

        $user->forceFill([
            'registration_state' => 'pending_2fa',
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['CODE-ONE'])),
            'two_factor_confirmed_at' => null,
        ])->save();

        $validCode = $google2fa->getCurrentOtp($secret);

        $this->mock(ConfirmTwoFactorAuthentication::class, function ($mock): void {
            $mock->shouldReceive('__invoke')
                ->once()
                ->andReturnUsing(function (User $user): void {
                    $user->forceFill([
                        'two_factor_confirmed_at' => now(),
                    ])->save();
                });
        });

        Livewire::actingAs($user)
            ->test(TwoFactor::class)
            ->set('verificationCode', $validCode)
            ->assertSet('codeIsValid', true)
            ->assertHasNoErrors();

        $this->assertSame('active', $user->fresh()->registration_state);
    }

    public function test_pending_user_two_factor_page_hides_profile_navigation_shortcuts(): void
    {
        $user = $this->makePendingUser();

        Livewire::actingAs($user)
            ->test(TwoFactor::class)
            ->assertDontSee('Profile Overview')
            ->assertDontSee('Edit Profile')
            ->assertSee('Sign Out');
    }

    private function makePendingUser(): User
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->individual()->create([
            'email' => 'pending-state@example.test',
            'nickname' => 'Pending State',
        ]);

        $user->forceFill([
            'registration_state' => 'pending_2fa',
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['CODE-ONE'])),
            'two_factor_confirmed_at' => null,
        ])->save();

        return $user->fresh();
    }
}
