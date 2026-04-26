<?php

namespace Tests\Unit\Services;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\ApplicationQueryService;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class ApplicationQueryServiceTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createJobPostingsTable();
        $this->createApplicationsTable();
    }

    public function test_company_queries_default_to_a_bounded_collection_size(): void
    {
        $company = User::factory()->company()->create();
        $this->seedCompanyApplications($company, 120);

        $results = app(ApplicationQueryService::class)->getApplicationsForUser($company);

        $this->assertCount(50, $results);
    }

    public function test_company_queries_clamp_requested_limit_to_maximum(): void
    {
        $company = User::factory()->company()->create();
        $this->seedCompanyApplications($company, 140);

        $results = app(ApplicationQueryService::class)->getApplicationsForUser($company, null, 1000);

        $this->assertCount(100, $results);
    }

    public function test_individual_queries_fallback_to_default_limit_for_invalid_values(): void
    {
        $applicant = User::factory()->individual()->create();
        $this->seedApplicantApplications($applicant, 80);

        $results = app(ApplicationQueryService::class)->getApplicationsForUser($applicant, null, 0);

        $this->assertCount(50, $results);
    }

    private function seedCompanyApplications(User $company, int $count): void
    {
        $applicant = User::factory()->individual()->create();

        for ($i = 0; $i < $count; $i++) {
            $job = JobPosting::factory()->for($company, 'companyUser')->create();
            Application::factory()
                ->for($job, 'jobPosting')
                ->for($applicant, 'applicantUser')
                ->create();
        }
    }

    private function seedApplicantApplications(User $applicant, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $company = User::factory()->company()->create();
            $job = JobPosting::factory()->for($company, 'companyUser')->create();
            Application::factory()
                ->for($job, 'jobPosting')
                ->for($applicant, 'applicantUser')
                ->create();
        }
    }
}
