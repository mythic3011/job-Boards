<?php

namespace Tests\Feature\Jobs;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class JobShowApplyCtaTest extends TestCase
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
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Setting::setBool('setup_completed', true);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_apply_cta_visible_for_individual_without_application(): void
    {
        $individual = $this->createUser(['user_type' => 'individual']);
        $job = $this->createJobPosting();

        $this->actingAs($individual)
            ->withBrowser()
            ->get(route('jobs.show', $job->idcode))
            ->assertOk()
            ->assertSee('Apply for this Job');
    }

    public function test_apply_cta_hidden_once_individual_has_applied(): void
    {
        $individual = $this->createUser(['user_type' => 'individual']);
        $job = $this->createJobPosting();
        $this->makeApplication($job, $individual);

        $this->actingAs($individual)
            ->withBrowser()
            ->get(route('jobs.show', $job->idcode))
            ->assertOk()
            ->assertDontSee('Apply for this Job')
            ->assertSee('View your application');
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

    private function createJobPosting(): JobPosting
    {
        $company = $this->createUser(['user_type' => 'company', 'nickname' => 'Test Corp']);

        return JobPosting::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'job_'.Str::uuid(),
            'company_user_id' => $company->id,
            'title' => 'Software Engineer',
            'requirement' => 'Build things',
            'duty' => 'Write code',
        ]);
    }

    private function makeApplication(JobPosting $job, User $applicant): Application
    {
        return Application::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'app_'.Str::uuid(),
            'job_id' => $job->id,
            'applicant_user_id' => $applicant->id,
            'message' => null,
            'cv_file_path' => 'cvs/test.pdf',
            'cv_original_name' => 'resume.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 100000,
            'cv_sha256' => bin2hex(random_bytes(32)),
            'status' => 'pending',
        ]);
    }
}
