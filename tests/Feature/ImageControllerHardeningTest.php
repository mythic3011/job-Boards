<?php

namespace Tests\Feature;

use App\Http\Controllers\ImageController;
use App\Models\User;
use App\Services\ProfileImageService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class ImageControllerHardeningTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    private const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO8B2y0AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createSettingsTable();
        $this->createAuditLogsTable();
    }

    public function test_profile_image_route_rejects_invalid_decoded_payloads(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->withBrowser()
            ->get(route('images.profile', ['path' => 'a']))
            ->assertNotFound();
    }

    public function test_controller_rejects_empty_decoded_paths(): void
    {
        $this->expectException(NotFoundHttpException::class);

        app(ImageController::class)->showPublicImage('');
    }

    public function test_profile_image_route_rejects_traversal_segments_after_normalization(): void
    {
        $user = $this->createUser();
        $encodedPath = ProfileImageService::encodePath('profile-images/../secret.png');

        $this->actingAs($user)
            ->withBrowser()
            ->get(route('images.profile', ['path' => $encodedPath]))
            ->assertNotFound();
    }

    public function test_public_image_route_rejects_traversal_segments_after_normalization(): void
    {
        $encodedPath = ProfileImageService::encodePath('uploads/../secret.txt');

        $this->withBrowser()
            ->get(route('images.public', ['path' => $encodedPath]))
            ->assertNotFound();
    }

    public function test_profile_image_response_includes_nosniff_header(): void
    {
        Storage::fake('private');

        $user = $this->createUser();
        $path = 'profile-images/avatar.png';
        Storage::disk('private')->put($path, base64_decode(self::ONE_PIXEL_PNG, true));

        $this->actingAs($user)
            ->withBrowser()
            ->get(route('images.profile', ['path' => ProfileImageService::encodePath($path)]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_public_image_response_includes_nosniff_header_without_image_only_restriction(): void
    {
        Storage::fake('public');

        $path = 'uploads/readme.txt';
        Storage::disk('public')->put($path, 'public attachment');

        $this->withBrowser()
            ->get(route('images.public', ['path' => ProfileImageService::encodePath($path)]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function createUser(): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_' . Str::uuid(),
            'login_id' => 'user_' . Str::lower(Str::random(6)),
            'nickname' => 'Image User',
            'email' => Str::lower(Str::random(8)) . '@example.test',
            'password' => Hash::make('StrongPass123!'),
            'user_type' => 'individual',
        ]);
    }
}
