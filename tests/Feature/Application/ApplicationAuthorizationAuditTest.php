<?php

namespace Tests\Feature\Application;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class ApplicationAuthorizationAuditTest extends TestCase
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
        $this->withoutMiddleware(VerifyCsrfToken::class);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('permissions')->insert([
            ['name' => 'download cv', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'manage applications', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_download_cv_permission_deny_creates_canonical_audit_log(): void
    {
        [$company, $application] = $this->makeOwnedApplicationFixture();

        $this->withBrowser()
            ->actingAs($company)
            ->get(route('applications.download-cv', $application->idcode))
            ->assertForbidden();

        $log = AuditLog::query()
            ->where('event_type', 'audit.application.download_cv.denied')
            ->latest('occurred_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('denied', $log->outcome);
        $this->assertSame('laravel', $log->source);
        $this->assertSame(403, $log->status_code);
        $this->assertSame($company->id, $log->actor_user_id);
        $this->assertSame('application', $log->target_type);
        $this->assertSame($application->idcode, $log->target_idcode);
        $this->assertSame('downloadCv', $log->meta['policy'] ?? null);
    }

    public function test_approve_permission_deny_creates_canonical_audit_log(): void
    {
        [$company, $application] = $this->makeOwnedApplicationFixture();

        $this->withBrowser()
            ->actingAs($company)
            ->post(route('applications.approve', $application->idcode), [
                'decision_message' => 'Denied in test',
            ])
            ->assertForbidden();

        $log = AuditLog::query()
            ->where('event_type', 'audit.application.approve.denied')
            ->latest('occurred_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('denied', $log->outcome);
        $this->assertSame('laravel', $log->source);
        $this->assertSame(403, $log->status_code);
        $this->assertSame($company->id, $log->actor_user_id);
        $this->assertSame('application', $log->target_type);
        $this->assertSame($application->idcode, $log->target_idcode);
        $this->assertSame('approve', $log->meta['policy'] ?? null);
    }

    public function test_reject_permission_deny_creates_canonical_audit_log(): void
    {
        [$company, $application] = $this->makeOwnedApplicationFixture();

        $this->withBrowser()
            ->actingAs($company)
            ->post(route('applications.reject', $application->idcode), [
                'decision_message' => 'Denied in test',
            ])
            ->assertForbidden();

        $log = AuditLog::query()
            ->where('event_type', 'audit.application.reject.denied')
            ->latest('occurred_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('denied', $log->outcome);
        $this->assertSame('laravel', $log->source);
        $this->assertSame(403, $log->status_code);
        $this->assertSame($company->id, $log->actor_user_id);
        $this->assertSame('application', $log->target_type);
        $this->assertSame($application->idcode, $log->target_idcode);
        $this->assertSame('reject', $log->meta['policy'] ?? null);
    }

    /**
     * @return array{0: User, 1: Application}
     */
    private function makeOwnedApplicationFixture(): array
    {
        $company = $this->createUser([
            'user_type' => 'company',
            'login_id' => 'company_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(6)) . '@example.com',
        ]);

        $applicant = $this->createUser([
            'user_type' => 'individual',
            'login_id' => 'applicant_' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(6)) . '@example.com',
        ]);

        $job = JobPosting::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'job_' . Str::uuid(),
            'company_user_id' => $company->id,
            'title' => 'Security Engineer',
            'requirement' => 'Own security roadmap',
            'duty' => 'Review applications',
        ]);

        $application = Application::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'app_' . Str::uuid(),
            'job_id' => $job->id,
            'applicant_user_id' => $applicant->id,
            'message' => 'Please review',
            'cv_file_path' => 'cvs/test.pdf',
            'cv_original_name' => 'test.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 1234,
            'cv_sha256' => hash('sha256', 'test'),
            'status' => 'pending',
        ]);

        return [$company, $application];
    }

    private function createUser(array $attributes): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_' . Str::uuid(),
            'nickname' => 'Test User',
            'password' => Hash::make('StrongPass123!'),
            ...$attributes,
        ]);
    }
}
