<?php

namespace Tests\Unit\Actions;

require_once __DIR__ . '/../../../app/Actions/Fortify/SendPasswordResetLinkWithTwoFactor.php';

use App\Actions\Fortify\SendPasswordResetLinkWithTwoFactor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class SendPasswordResetLinkWithTwoFactorTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createAuditLogsTable();

        $request = Request::create('/forgot-password', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);
        $this->app->instance('request', $request);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_local_mode_does_not_return_raw_reset_token_in_action_payload(): void
    {
        $previousEnv = $this->app->environment();
        $this->app->instance('env', 'local');

        try {
            $user = User::factory()->create([
                'email' => 'user@example.com',
                'two_factor_secret' => encrypt('secret'),
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => encrypt(json_encode(['ABCD-EFGH-IJKL'])),
            ]);

            Password::shouldReceive('broker->createToken')
                ->once()
                ->with(Mockery::on(fn (User $candidate) => $candidate->is($user)))
                ->andReturn('raw-reset-token');
            RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(false);
            RateLimiter::shouldReceive('clear')->once();
            Log::shouldReceive('debug')
                ->once()
                ->with(
                    'Password reset token generated (LOCAL ONLY)',
                    Mockery::on(function (array $context): bool {
                        return ($context['token_generated'] ?? false) === true
                            && ! array_key_exists('token', $context)
                            && ! array_key_exists('reset_url', $context)
                            && ! array_key_exists('email', $context);
                    })
                );

            $action = app(SendPasswordResetLinkWithTwoFactor::class);

            $result = $action([
                'email' => $user->email,
                'recovery_code' => 'ABCD-EFGH-IJKL',
            ]);

            $this->assertSame(Password::RESET_LINK_SENT, $result['status']);
            $this->assertTrue($result['local']);
            $this->assertArrayNotHasKey('token', $result, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->assertArrayNotHasKey('email', $result, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } finally {
            $this->app->instance('env', $previousEnv);
        }
    }
}
