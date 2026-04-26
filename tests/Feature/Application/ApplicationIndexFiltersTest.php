<?php

namespace Tests\Feature\Application;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class ApplicationIndexFiltersTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createSettingsTable();
        $this->createJobPostingsTable();
        $this->createApplicationsTable();

        DB::table('settings')->insert([
            'key' => 'setup_completed',
            'value' => 'true',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_individual_application_index_search_works_on_sqlite(): void
    {
        $applicant = $this->makeUser('individual', 'Applicant');
        $company = $this->makeUser('company', 'Acme Labs');

        $engineerJob = $this->makeJob($company, 'Platform Engineer');
        $designerJob = $this->makeJob($company, 'Graphic Designer');

        $this->makeApplication($engineerJob, $applicant, 'approved');
        $this->makeApplication($designerJob, $applicant, 'pending');

        Volt::actingAs($applicant)->test('applications.index')
            ->set('search', 'Engineer')
            ->assertSee('Platform Engineer')
            ->assertDontSee('Graphic Designer');
    }

    public function test_clear_filters_resets_search_and_status_filter_state(): void
    {
        $applicant = $this->makeUser('individual', 'Applicant');
        $company = $this->makeUser('company', 'Acme Labs');

        $engineerJob = $this->makeJob($company, 'Platform Engineer');
        $designerJob = $this->makeJob($company, 'Graphic Designer');

        $this->makeApplication($engineerJob, $applicant, 'approved');
        $this->makeApplication($designerJob, $applicant, 'pending');

        Volt::actingAs($applicant)->test('applications.index')
            ->set('search', 'Engineer')
            ->set('statusFilter', 'approved')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('statusFilter', '')
            ->assertSee('Platform Engineer')
            ->assertSee('Graphic Designer');
    }

    public function test_company_job_scoped_index_shows_filtered_job_title_context(): void
    {
        $company = $this->makeUser('company', 'Acme Labs');
        $applicant = $this->makeUser('individual', 'Applicant');

        $engineerJob = $this->makeJob($company, 'Platform Engineer');
        $designerJob = $this->makeJob($company, 'Graphic Designer');

        $this->makeApplication($engineerJob, $applicant, 'pending');
        $this->makeApplication($designerJob, $applicant, 'pending');

        Volt::actingAs($company)->test('applications.index', ['jobIdcode' => $engineerJob->idcode])
            ->assertSee('Applications for: Platform Engineer')
            ->assertSee('Platform Engineer')
            ->assertDontSee('Graphic Designer');
    }

    public function test_individual_job_scoped_index_shows_filtered_job_title_context(): void
    {
        $applicant = $this->makeUser('individual', 'Applicant');
        $company = $this->makeUser('company', 'Acme Labs');

        $engineerJob = $this->makeJob($company, 'Platform Engineer');
        $designerJob = $this->makeJob($company, 'Graphic Designer');

        $this->makeApplication($engineerJob, $applicant, 'pending');
        $this->makeApplication($designerJob, $applicant, 'approved');

        Volt::actingAs($applicant)->test('applications.index', ['jobIdcode' => $engineerJob->idcode])
            ->assertSee('Applications for: Platform Engineer')
            ->assertSee('Platform Engineer')
            ->assertDontSee('Graphic Designer');
    }

    private function makeUser(string $type, string $nickname): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_'.Str::uuid(),
            'login_id' => Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'nickname' => $nickname,
            'password' => Hash::make('StrongPass123!'),
            'user_type' => $type,
        ]);
    }

    private function makeJob(User $company, string $title): JobPosting
    {
        return JobPosting::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'job_'.Str::uuid(),
            'company_user_id' => $company->id,
            'title' => $title,
            'requirement' => 'Relevant experience required',
            'duty' => 'Build and support the product',
        ]);
    }

    private function makeApplication(JobPosting $job, User $applicant, string $status): Application
    {
        return Application::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'app_'.Str::uuid(),
            'job_id' => $job->id,
            'applicant_user_id' => $applicant->id,
            'message' => 'Interested candidate',
            'cv_file_path' => 'cvs/test.pdf',
            'cv_original_name' => 'resume.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 1024,
            'cv_sha256' => bin2hex(random_bytes(32)),
            'status' => $status,
        ]);
    }
}
