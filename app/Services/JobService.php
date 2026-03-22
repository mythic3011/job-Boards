<?php

namespace App\Services;

use App\Models\JobPosting;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JobService
{
    public function __construct(
        private readonly Guard $auth,
        private readonly Request $request,
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * Create a new job posting.
     *
     * @param  array{title: string, requirement: string, duty: string, salary_from?: int|null, salary_to?: int|null}  $data
     */
    public function createJob(array $data): JobPosting
    {
        $job = JobPosting::create([
            'company_user_id' => $this->auth->id(),
            'title' => $data['title'],
            'requirement' => $data['requirement'],
            'duty' => $data['duty'],
            'salary_from' => $data['salary_from'] ?? null,
            'salary_to' => $data['salary_to'] ?? null,
        ]);

        $this->logJobCreation($job);

        return $job;
    }

    /**
     * Log job creation for audit purposes.
     */
    private function logJobCreation(JobPosting $job): void
    {
        $user = $this->auth->user();

        $this->auditLogger->logBusinessEvent(
            eventType: 'job.created',
            request: $this->request,
            targetType: 'job',
            targetIdcode: $job->idcode,
            meta: [
                'job_id' => $job->id,
                'job_idcode' => $job->idcode,
                'title' => $job->title,
                'actor_user_id' => $user?->id,
                'actor_user_type' => $user?->user_type,
                'actor_roles' => $user ? $user->roles()->pluck('name')->values()->all() : [],
            ]
        );

        Log::info('Job created', [
            'user_id' => $this->auth->id(),
            'job_id' => $job->id,
            'job_idcode' => $job->idcode,
            'ip' => $this->request->ip(),
        ]);
    }
}
