<?php

namespace Tests\Feature\Registration;

use App\Services\AuditLogger;
use App\Services\ProfileImageService;
use App\Services\TwoFactorService;
use App\Services\UserRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class RegistrationAtomicityTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_failed_profile_image_store_rolls_back_user_creation(): void
    {
        $profileImageService = Mockery::mock(ProfileImageService::class);
        $profileImageService->shouldReceive('storeImage')
            ->once()
            ->andThrow(new \InvalidArgumentException('bad image'));

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldNotReceive('logBusinessEvent');

        $twoFactorService = Mockery::mock(TwoFactorService::class);

        $service = new UserRegistrationService($profileImageService, $auditLogger, $twoFactorService);

        try {
            $service->register([
                'login_id' => 'alice',
                'nickname' => 'Alice',
                'email' => 'alice@example.com',
                'user_type' => 'individual',
                'password' => 'Sup3rSecret!Pass99',
                'password_confirmation' => 'Sup3rSecret!Pass99',
                'profile_image' => UploadedFile::fake()->image('x.jpg'),
            ], Request::create('/register', 'POST'));
            $this->fail('Expected exception');
        } catch (\Throwable) {
            // expected
        }

        $this->assertDatabaseMissing('users', ['login_id' => 'alice']);
    }

    public function test_post_storage_failure_deletes_profile_image_and_rolls_back_user_creation(): void
    {
        $storedPath = 'profile-images/alice.jpg';

        $profileImageService = Mockery::mock(ProfileImageService::class);
        $profileImageService->shouldReceive('storeImage')
            ->once()
            ->andReturn($storedPath);
        $profileImageService->shouldReceive('deleteImage')
            ->once()
            ->with($storedPath);

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldNotReceive('logBusinessEvent');

        $twoFactorService = Mockery::mock(TwoFactorService::class);
        $twoFactorService->shouldReceive('enable')
            ->once()
            ->andThrow(new \RuntimeException('2FA setup failed'));

        $service = new UserRegistrationService($profileImageService, $auditLogger, $twoFactorService);

        try {
            $service->register([
                'login_id' => 'alice-twofa',
                'nickname' => 'Alice 2FA',
                'email' => 'alice-twofa@example.com',
                'user_type' => 'individual',
                'password' => 'Sup3rSecret!Pass99',
                'password_confirmation' => 'Sup3rSecret!Pass99',
                'profile_image' => UploadedFile::fake()->image('alice.jpg'),
                'enable_2fa' => true,
            ], Request::create('/register', 'POST'));
            $this->fail('Expected exception');
        } catch (\Throwable) {
            // expected
        }

        $this->assertDatabaseMissing('users', ['login_id' => 'alice-twofa']);
    }
}
