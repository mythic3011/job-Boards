<?php

namespace Tests\Feature\Jobs;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class JobsIndexEmptyStateTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createSettingsTable();
        $this->createJobPostingsTable();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Setting::setBool('setup_completed', true);
    }

    public function test_signed_in_individual_sees_no_register_cta_on_empty_state(): void
    {
        $individual = $this->createUser(['user_type' => 'individual']);

        $this->actingAs($individual)
            ->withBrowser()
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertDontSee('register to apply');
    }

    public function test_signed_in_individual_sees_browse_hint_on_empty_state(): void
    {
        $individual = $this->createUser(['user_type' => 'individual']);

        $this->actingAs($individual)
            ->withBrowser()
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('Check back soon - new roles are posted regularly.');
    }

    private function createUser(array $attributes): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_'.Str::uuid(),
            'login_id' => Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'nickname' => 'Test User',
            'password' => Hash::make('StrongPass123!'),
            ...$attributes,
        ]);
    }
}
