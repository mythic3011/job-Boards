<?php

namespace Tests\Feature\Performance;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class PerformanceQuickWinsTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createAuditLogsTable();
        $this->createSettingsTable();
        $this->createPermissionTables();
        $this->createJobPostingsTable();
        $this->createApplicationsTable();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_jobs_index_uses_a_bounded_query_for_application_counts(): void
    {
        $company = User::factory()->company()->create();

        JobPosting::factory()
            ->count(30)
            ->for($company, 'companyUser')
            ->create()
            ->each(function (JobPosting $job): void {
                Application::factory()->count(2)->for($job, 'jobPosting')->create();
            });

        $perRowApplicationCountQueries = 0;
        DB::listen(function ($query) use (&$perRowApplicationCountQueries): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, 'count(*)')
                && str_contains($sql, 'applications')
                && (
                    str_contains($sql, '"applications"."job_id" = ?')
                    || str_contains($sql, '`applications`.`job_id` = ?')
                    || str_contains($sql, 'applications.job_id = ?')
                )) {
                $perRowApplicationCountQueries++;
            }
        });

        Volt::test('admin.jobs.index')
            ->set('visibleCount', 30)
            ->assertSee((string) 2);

        $this->assertLessThanOrEqual(
            1,
            $perRowApplicationCountQueries,
            'Application counts should be loaded with the jobs query, not counted once per rendered job row.'
        );
    }

    public function test_admin_users_index_loads_header_stats_with_one_user_aggregate_query(): void
    {
        User::factory()->create([
            'user_type' => 'admin',
            'locked_until' => now()->addDay(),
            'two_factor_confirmed_at' => now(),
        ]);
        User::factory()->company()->create(['two_factor_confirmed_at' => now()]);
        User::factory()->individual()->create();

        $legacyPerStatCountQueries = 0;
        $statsAggregateQueries = 0;
        DB::listen(function ($query) use (&$legacyPerStatCountQueries, &$statsAggregateQueries): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, 'count(*)')
                && str_contains($sql, 'from')
                && str_contains($sql, 'users')
                && (
                    str_contains($sql, 'user_type')
                    || str_contains($sql, 'locked_until')
                    || str_contains($sql, 'two_factor_confirmed_at')
                )
                && str_contains($sql, 'count(*) as aggregate')) {
                $legacyPerStatCountQueries++;
            }

            if (str_contains($sql, 'sum(case when user_type')
                && str_contains($sql, 'sum(case when locked_until')
                && str_contains($sql, 'sum(case when two_factor_confirmed_at')) {
                $statsAggregateQueries++;
            }
        });

        Volt::test('admin.users.index')
            ->assertSee('Total Users')
            ->assertSee('Admin Accounts')
            ->assertSee('Locked Accounts')
            ->assertSee('2FA Enabled');

        $this->assertSame(
            0,
            $legacyPerStatCountQueries,
            'Admin users index should avoid legacy per-stat count queries in render path.'
        );
        $this->assertGreaterThanOrEqual(
            1,
            $statsAggregateQueries,
            'Admin users index should load header stats using one aggregate query.'
        );
    }

    public function test_cv_download_returns_the_file_without_controller_buffering_the_cv_body(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/ApplicationController.php');

        $this->assertIsString($controller);
        $this->assertStringNotContainsString('->get($application->cv_file_path)', $controller);

        DB::table('permissions')->insert([
            ['name' => 'download cv', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Storage::fake('private');
        Storage::disk('private')->put('cvs/test.pdf', 'streamed-cv-content');

        [$company, $application] = $this->makeOwnedApplicationFixture('cvs/test.pdf');
        $company->givePermissionTo('download cv');

        $response = $this->withBrowser()
            ->actingAs($company)
            ->get(route('applications.download-cv', $application->idcode));

        $response->assertOk();
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition', ''));
        $this->assertStringContainsString('candidate.pdf', $response->headers->get('content-disposition', ''));
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_non_canonical_audit_metadata_caps_large_list_values(): void
    {
        $ids = array_map(fn (int $i): string => 'user-'.$i, range(1, 75));

        app(AuditLogger::class)->logBusinessEvent(
            eventType: 'demo.data_cleared',
            request: request(),
            targetType: 'system',
            targetIdcode: null,
            meta: [
                'users_deleted' => count($ids),
                'user_ids' => $ids,
            ],
        );

        $meta = AuditLog::query()->where('event_type', 'demo.data_cleared')->firstOrFail()->meta;

        $this->assertSame(75, $meta['user_ids']['count']);
        $this->assertCount(20, $meta['user_ids']['sample']);
        $this->assertTrue($meta['user_ids']['truncated']);
        $this->assertSame('user-1', $meta['user_ids']['sample'][0]);
    }

    public function test_admin_company_filter_option_queries_are_capped(): void
    {
        $jobsView = file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/admin/jobs/index.blade.php');
        $applicationsView = file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/admin/applications/index.blade.php');

        $this->assertIsString($jobsView);
        $this->assertIsString($applicationsView);
        $this->assertStringContainsString('private const COMPANY_FILTER_LIMIT = 200;', $jobsView);
        $this->assertStringContainsString('private const COMPANY_FILTER_LIMIT = 200;', $applicationsView);
        $this->assertStringContainsString('->limit(self::COMPANY_FILTER_LIMIT)', $jobsView);
        $this->assertStringContainsString('->limit(self::COMPANY_FILTER_LIMIT)', $applicationsView);
    }

    /**
     * @return array{0: User, 1: Application}
     */
    private function makeOwnedApplicationFixture(string $path): array
    {
        $company = $this->createUser([
            'user_type' => 'company',
            'login_id' => 'company_'.Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(6)).'@example.com',
        ]);

        $applicant = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'applicant_'.Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(6)).'@example.com',
        ]);

        $job = JobPosting::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'job_'.Str::uuid(),
            'company_user_id' => $company->id,
            'title' => 'Security Engineer',
            'requirement' => 'Own security roadmap',
            'duty' => 'Review applications',
        ]);

        $application = Application::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'app_'.Str::uuid(),
            'job_id' => $job->id,
            'applicant_user_id' => $applicant->id,
            'message' => 'Please review',
            'cv_file_path' => $path,
            'cv_original_name' => 'candidate.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 19,
            'cv_sha256' => hash('sha256', 'streamed-cv-content'),
            'status' => 'pending',
        ]);

        return [$company, $application];
    }

    private function createUser(array $attributes): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_'.Str::uuid(),
            'nickname' => 'Test User',
            'password' => Hash::make('StrongPass123!'),
            ...$attributes,
        ]);
    }
}
