<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminJobModerationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function deleteJob(User $actor, JobPosting $jobPosting, Request $request): void
    {
        Gate::forUser($actor)->authorize('adminModerate', $jobPosting);

        $this->auditLogger->logBusinessEvent(
            eventType: 'admin.job.deleted',
            request: $request,
            targetType: 'job',
            targetIdcode: $jobPosting->idcode,
            meta: [
                'job_title' => $jobPosting->title,
                'company_user_id' => $jobPosting->company_user_id,
            ]
        );

        $jobPosting->delete();
        $this->dashboardService->clearCache();
    }
}
