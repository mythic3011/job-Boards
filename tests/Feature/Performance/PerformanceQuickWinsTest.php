<?php

namespace Tests\Feature\Performance;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DashboardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
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

    public function test_admin_company_filter_options_query_is_cached_across_admin_render_paths(): void
    {
        Cache::flush();

        User::factory()->company()->count(3)->create();

        $companyOptionsQueries = 0;
        DB::listen(function ($query) use (&$companyOptionsQueries): void {
            $sql = strtolower($query->sql);

            $isUsersQuery = str_contains($sql, 'from "users"')
                || str_contains($sql, 'from `users`')
                || str_contains($sql, 'from users');

            if ($isUsersQuery
                && str_contains($sql, 'user_type')
                && in_array('company', $query->bindings, true)) {
                $companyOptionsQueries++;
            }
        });

        Volt::test('admin.jobs.index')->assertSee('All companies');
        Volt::test('admin.applications.index')->assertSee('All companies');

        $this->assertLessThanOrEqual(
            1,
            $companyOptionsQueries,
            'Company filter options should be cached and not re-queried for each admin render path.'
        );
    }

    public function test_admin_applications_index_uses_bounded_aggregate_queries_for_stats_cards(): void
    {
        $company = User::factory()->company()->create();

        for ($i = 1; $i <= 35; $i++) {
            $job = JobPosting::factory()
                ->for($company, 'companyUser')
                ->create();

            $applicant = User::factory()->create();

            Application::factory()
                ->for($job, 'jobPosting')
                ->for($applicant, 'applicantUser')
                ->state([
                    'status' => $i % 2 === 0 ? 'approved' : 'pending',
                    'cv_original_name' => "candidate-{$i}.pdf",
                ])
                ->create();
        }

        $perCardApplicationCountQueries = 0;
        DB::listen(function ($query) use (&$perCardApplicationCountQueries): void {
            $sql = strtolower($query->sql);

            if (! str_contains($sql, 'count(*)') || ! str_contains($sql, 'applications')) {
                return;
            }

            $targetsStatus = str_contains($sql, 'status') && str_contains($sql, 'where');
            $targetsCv = str_contains($sql, 'cv_original_name') && str_contains($sql, 'where');

            if ($targetsStatus || $targetsCv) {
                $perCardApplicationCountQueries++;
            }
        });

        Volt::test('admin.applications.index')
            ->set('visibleCount', 30)
            ->assertSee('Application queue');

        $this->assertSame(
            0,
            $perCardApplicationCountQueries,
            'Admin applications stats should not issue separate COUNT queries per status/CV card.'
        );
    }

    public function test_admin_users_index_uses_bounded_aggregate_queries_for_stats_cards(): void
    {
        User::factory()->count(45)->create();

        $lockedUser = User::factory()->create([
            'locked_until' => now()->addHour(),
        ]);

        $twoFactorUser = User::factory()->create([
            'two_factor_confirmed_at' => now(),
        ]);

        $adminUser = User::factory()->create([
            'user_type' => 'admin',
        ]);

        $this->assertNotNull($lockedUser->id);
        $this->assertNotNull($twoFactorUser->id);
        $this->assertNotNull($adminUser->id);

        $perCardUserCountQueries = 0;
        DB::listen(function ($query) use (&$perCardUserCountQueries): void {
            $sql = strtolower($query->sql);

            if (! str_contains($sql, 'count(*)') || ! str_contains($sql, 'from') || ! str_contains($sql, 'users')) {
                return;
            }

            $targetsUserType = str_contains($sql, 'user_type') && str_contains($sql, 'where');
            $targetsLock = str_contains($sql, 'locked_until') && str_contains($sql, 'where');
            $targetsTwoFactor = str_contains($sql, 'two_factor_confirmed_at') && str_contains($sql, 'where');

            if ($targetsUserType || $targetsLock || $targetsTwoFactor) {
                $perCardUserCountQueries++;
            }
        });

        Volt::test('admin.users.index')
            ->set('visibleCount', 30)
            ->assertSee('User operations');

        $this->assertSame(
            0,
            $perCardUserCountQueries,
            'Admin users stats should not issue separate COUNT queries per role/lock/2FA card.'
        );
    }

    public function test_dashboard_stats_use_bounded_query_budget_on_hot_tables(): void
    {
        $company = User::factory()->company()->create();
        $individual = User::factory()->create();

        $job = JobPosting::factory()->for($company, 'companyUser')->create();
        JobPosting::factory()->count(2)->for($company, 'companyUser')->create();
        Application::factory()->count(4)
            ->for($job, 'jobPosting')
            ->for($individual, 'applicantUser')
            ->create();
        AuditLog::factory()->count(5)->create([
            'occurred_at' => now(),
        ]);

        $dashboardService = app(DashboardService::class);
        $dashboardService->clearCache();

        $hotTableQueries = 0;
        DB::listen(function ($query) use (&$hotTableQueries): void {
            $sql = strtolower($query->sql);

            $touchesHotTable = str_contains($sql, 'from "users"')
                || str_contains($sql, 'from `users`')
                || str_contains($sql, 'from users')
                || str_contains($sql, 'from "job_postings"')
                || str_contains($sql, 'from `job_postings`')
                || str_contains($sql, 'from job_postings')
                || str_contains($sql, 'from "applications"')
                || str_contains($sql, 'from `applications`')
                || str_contains($sql, 'from applications')
                || str_contains($sql, 'from "audit_logs"')
                || str_contains($sql, 'from `audit_logs`')
                || str_contains($sql, 'from audit_logs');

            if ($touchesHotTable) {
                $hotTableQueries++;
            }
        });

        $stats = $dashboardService->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('total_jobs', $stats);
        $this->assertArrayHasKey('total_applications', $stats);
        $this->assertArrayHasKey('events_today', $stats);
        $this->assertLessThanOrEqual(
            6,
            $hotTableQueries,
            'Dashboard stats should stay within a bounded query budget for users/jobs/applications/audit hot tables.'
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

    public function test_admin_users_index_uses_single_aggregate_stats_query_shape(): void
    {
        $usersIndex = file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/admin/users/index.blade.php');

        $this->assertIsString($usersIndex);
        $this->assertStringContainsString("selectRaw('COUNT(*) as total_users')", $usersIndex);
        $this->assertStringContainsString("SUM(CASE WHEN user_type = 'admin' THEN 1 ELSE 0 END) as admin_users", $usersIndex);
        $this->assertStringContainsString("SUM(CASE WHEN locked_until IS NOT NULL AND locked_until > ? THEN 1 ELSE 0 END) as locked_users", $usersIndex);
        $this->assertStringContainsString('SUM(CASE WHEN two_factor_confirmed_at IS NOT NULL THEN 1 ELSE 0 END) as two_factor_users', $usersIndex);
        $this->assertStringNotContainsString("'admin_users' => User::where('user_type', 'admin')->count()", $usersIndex);
        $this->assertStringNotContainsString("'locked_users' => User::whereNotNull('locked_until')->where('locked_until', '>', now())->count()", $usersIndex);
        $this->assertStringNotContainsString("'two_factor_users' => User::whereNotNull('two_factor_confirmed_at')->count()", $usersIndex);
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
