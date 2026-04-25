<?php

namespace Tests\Feature\Profile;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ProfileImageService;
use App\Services\ProfileService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class ProfileImageAtomicReplacementTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createAuditLogsTable();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_profile_service_failed_image_store_preserves_existing_avatar(): void
    {
        $user = User::factory()->create([
            'nickname' => 'Valid Tester',
            'email' => 'valid-profile-service@example.test',
            'profile_image_path' => 'profile-images/existing.png',
        ]);

        $profileImageService = Mockery::mock(ProfileImageService::class);
        $profileImageService->shouldReceive('storeImage')
            ->once()
            ->andThrow(new \InvalidArgumentException('bad image'));
        $profileImageService->shouldReceive('deleteImage')->never();

        $twoFactorService = Mockery::mock(TwoFactorService::class);
        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldNotReceive('logBusinessEvent');

        $service = new ProfileService($profileImageService, $twoFactorService, $auditLogger);

        try {
            $service->updateProfile($user, [
                'nickname' => $user->nickname,
                'email' => $user->email,
                'profile_image' => UploadedFile::fake()->image('new.jpg'),
            ], Request::create('/profile', 'PUT'));
            $this->fail('Expected validation exception');
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        $this->assertSame('profile-images/existing.png', $user->fresh()->profile_image_path);
    }

    public function test_fortify_update_failed_image_store_preserves_existing_avatar(): void
    {
        $user = User::factory()->create([
            'nickname' => 'Valid Fortify',
            'email' => 'valid-fortify@example.test',
            'profile_image_path' => 'profile-images/existing.png',
        ]);

        $profileImageService = Mockery::mock(ProfileImageService::class);
        $profileImageService->shouldReceive('storeImage')
            ->once()
            ->andThrow(new \InvalidArgumentException('bad image'));
        $profileImageService->shouldReceive('deleteImage')->never();

        $action = new UpdateUserProfileInformation($profileImageService);

        try {
            $action->update($user, [
                'nickname' => $user->nickname,
                'email' => $user->email,
                'profile_image' => UploadedFile::fake()->image('new.jpg'),
            ]);
            $this->fail('Expected image validation exception');
        } catch (\InvalidArgumentException) {
            // expected
        }

        $this->assertSame('profile-images/existing.png', $user->fresh()->profile_image_path);
    }
}
