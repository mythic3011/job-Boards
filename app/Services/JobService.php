<?php

namespace App\Services;

use App\Models\JobPosting;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JobService
{
    public function __construct(
        private readonly Guard $auth,
        private readonly Request $request
    ) {
    }

    /**
     * Create a new job posting.
     *
     * @param  array{title: string, requirement: string, duty: string, salary?: string|null}  $data
     */
    public function createJob(array $data): JobPosting
    {
        $job = JobPosting::create([
            'company_user_id' => $this->auth->id(),
            'title' => $data['title'],
            'requirement' => $data['requirement'],
            'duty' => $data['duty'],
            'salary' => $data['salary'] ?? null,
        ]);

        $this->logJobCreation($job);

        return $job;
    }

    /**
     * Log job creation for audit purposes.
     */
    private function logJobCreation(JobPosting $job): void
    {
        Log::info('Job created', [
            'user_id' => $this->auth->id(),
            'job_id' => $job->id,
            'job_idcode' => $job->idcode,
            'ip' => $this->request->ip(),
        ]);
    }
}
