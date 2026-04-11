<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class AntiBotShadowModeTest extends TestCase
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
        Setting::setBool('maintenance_mode', false);
        Cache::forget('setting.maintenance_mode');

        config([
            'anti_bot.enabled' => true,
            'anti_bot.surfaces.install.mode' => 'shadow',
            'anti_bot.surfaces.login.mode' => 'shadow',
            'anti_bot.surfaces.admin.mode' => 'shadow',
        ]);

        Route::middleware(['web', 'anti-bot.install'])
            ->get('/_test/anti-bot/install', fn () => response('install-shadow-ok'))
            ->name('test.anti-bot.install');

        Route::middleware(['web', 'auth', 'anti-bot.admin'])
            ->get('/_test/anti-bot/admin', fn () => response('admin-shadow-ok'))
            ->name('test.anti-bot.admin');
    }

    public function test_install_shadow_mode_records_hypothetical_decision_without_changing_response(): void
    {
        $response = $this->withBrowser()
            ->get('/_test/anti-bot/install');

        $response->assertOk()
            ->assertSee('install-shadow-ok');

        $log = AuditLog::query()->where('event_type', 'anti_bot.risk_scored')->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('allow', $log->meta['decision']);
        $this->assertSame('low', $log->meta['risk_bucket']);
        $this->assertSame('install', $log->meta['surface']);
        $this->assertTrue($log->meta['shadow_mode']);
    }

    public function test_login_shadow_mode_records_malformed_pending_login_state_on_real_route_without_changing_response(): void
    {
        $response = $this->withBrowser()
            ->withSession(['login.id' => ['not-a-scalar']])
            ->get(route('login'));

        $response->assertOk();

        $log = AuditLog::query()->where('event_type', 'anti_bot.risk_scored')->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('login', $log->meta['surface']);
        $this->assertSame('allow', $log->meta['decision']);
        $this->assertSame('low', $log->meta['risk_bucket']);
        $this->assertSame('malformed', $log->meta['pending_login_state']);
        $this->assertArrayNotHasKey('deny_reason', $log->meta);
    }

    public function test_fortify_login_and_two_factor_routes_include_anti_bot_login_middleware(): void
    {
        foreach (['login', 'login.store', 'two-factor.login', 'two-factor.login.store'] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Expected route [{$routeName}] to exist.");
            $this->assertContains('anti-bot.login', $route->gatherMiddleware(), "Expected route [{$routeName}] to include anti-bot.login middleware.");
        }
    }

    public function test_two_factor_shadow_mode_records_two_factor_surface_on_real_route_without_changing_response(): void
    {
        $response = $this->withBrowser()
            ->withSession(['login.id' => ['not-a-scalar']])
            ->get(route('two-factor.login'));

        $response->assertOk();

        $log = AuditLog::query()->where('event_type', 'anti_bot.risk_scored')->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('two_factor', $log->meta['surface']);
        $this->assertSame('allow', $log->meta['decision']);
        $this->assertSame('low', $log->meta['risk_bucket']);
        $this->assertSame('malformed', $log->meta['pending_login_state']);
        $this->assertTrue($log->meta['shadow_mode']);
    }

    public function test_two_factor_shadow_mode_records_expected_but_missing_pending_login_state_separately(): void
    {
        $response = $this->withBrowser()
            ->get(route('two-factor.login'));

        $response->assertRedirect(route('login'));

        $log = AuditLog::query()->where('event_type', 'anti_bot.risk_scored')->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('two_factor', $log->meta['surface']);
        $this->assertSame('missing', $log->meta['pending_login_state']);
        $this->assertTrue($log->meta['pending_login_expected']);
        $this->assertTrue($log->meta['pending_login_flow']);
        $this->assertContains('pending_login_expected_but_missing', $log->meta['signals']);
    }

    public function test_admin_shadow_mode_records_actor_context_without_changing_response(): void
    {
        $admin = $this->createUser([
            'user_type' => 'admin',
            'login_id' => 'admin-shadow',
            'email' => 'admin-shadow@example.com',
        ]);

        $response = $this->withBrowser()
            ->actingAs($admin)
            ->get('/_test/anti-bot/admin');

        $response->assertOk()
            ->assertSee('admin-shadow-ok');

        $log = AuditLog::query()->where('event_type', 'anti_bot.risk_scored')->latest('occurred_at')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame('admin', $log->meta['surface']);
        $this->assertSame('allow', $log->meta['decision']);
        $this->assertSame('low', $log->meta['risk_bucket']);
        $this->assertTrue($log->meta['shadow_mode']);
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
