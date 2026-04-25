<?php

namespace Tests\Feature\Application;

use App\Models\JobPosting;
use App\Models\User;
use App\Services\ProfileImageService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Mockery;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class ApplicationCreateUploadFeedbackTest extends TestCase
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
        $this->createApplicationsTable();
        $this->createPermissionTables();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        DB::table('settings')->insert([
            'key' => 'setup_completed',
            'value' => 'true',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('permissions')->insert([
            'name' => 'apply to jobs',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_profile_image_selection_marks_photo_as_ready_instead_of_uploaded(): void
    {
        $user = User::factory()->individual()->create();
        $job = JobPosting::factory()->create();
        $this->grantPermission($user, 'apply to jobs');

        Volt::actingAs($user)->test('applications.create', ['jobIdcode' => $job->idcode])
            ->set('profile_image', UploadedFile::fake()->image('photo.jpg'))
            ->assertSet('profileImageNotice', 'Photo ready. It will be saved when you submit.')
            ->assertSee('Photo ready. It will be saved when you submit.')
            ->assertDontSee('successfully uploaded');
    }

    public function test_livewire_submit_failure_routes_profile_image_error_to_profile_image_field(): void
    {
        $user = User::factory()->individual()->create();
        $job = JobPosting::factory()->create();
        $this->grantPermission($user, 'apply to jobs');

        $this->mock(ProfileImageService::class, function (Mockery\MockInterface $mock): void {
            $mock->shouldReceive('storeImage')
                ->once()
                ->andThrow(new \InvalidArgumentException('bad image'));
            $mock->shouldReceive('deleteImage')->never();
        });

        Volt::actingAs($user)->test('applications.create', ['jobIdcode' => $job->idcode])
            ->set('profile_image', UploadedFile::fake()->image('photo.jpg'))
            ->set('cv_file', UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf'))
            ->call('submit')
            ->assertHasErrors(['profile_image'])
            ->assertHasNoErrors(['cv_file']);
    }

    public function test_controller_fallback_submit_failure_routes_profile_image_error_to_profile_image_field(): void
    {
        $user = User::factory()->individual()->create();
        $job = JobPosting::factory()->create();
        $this->grantPermission($user, 'apply to jobs');

        $this->mock(ProfileImageService::class, function (Mockery\MockInterface $mock): void {
            $mock->shouldReceive('storeImage')
                ->once()
                ->andThrow(new \InvalidArgumentException('bad image'));
            $mock->shouldReceive('deleteImage')->never();
        });

        $this->actingAs($user)
            ->withBrowser()
            ->from(route('applications.create', $job->idcode))
            ->post(route('applications.store', $job->idcode), [
                'profile_image' => UploadedFile::fake()->image('photo.jpg'),
                'cv_file' => UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect(route('applications.create', $job->idcode))
            ->assertSessionHasErrors(['profile_image'])
            ->assertSessionDoesntHaveErrors(['cv_file']);
    }

    private function grantPermission(User $user, string $permission): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', $permission)
            ->where('guard_name', 'web')
            ->value('id');

        DB::table('model_has_permissions')->insert([
            'permission_id' => $permissionId,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
