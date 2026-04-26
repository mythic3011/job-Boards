<?php

namespace App\Services;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;

class ApplicationQueryService
{
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 100;

    /**
     * Get applications for a user based on their role.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getApplicationsForUser(User $user, ?string $jobIdcode = null, int $limit = self::DEFAULT_LIMIT)
    {
        $boundedLimit = $this->normalizeLimit($limit);

        if ($user->isCompany()) {
            return $this->getCompanyApplications($user, $jobIdcode, $boundedLimit);
        }

        return $this->getIndividualApplications($user, $boundedLimit);
    }

    /**
     * Get applications for a company user.
     */
    private function getCompanyApplications(User $user, ?string $jobIdcode = null, int $limit = self::DEFAULT_LIMIT)
    {
        $query = Application::forCompanyJobs($user->id);

        if ($jobIdcode) {
            // OWASP A01: Scope job lookup to company owner
            $job = JobPosting::byIdcode($jobIdcode)
                ->byCompany($user->id)
                ->firstOrFail();
            $query->forJob($job->id);
        }

        return $query->with(['jobPosting', 'applicantUser'])->latest()->limit($limit)->get();
    }

    /**
     * Get applications for an individual user.
     */
    private function getIndividualApplications(User $user, int $limit = self::DEFAULT_LIMIT)
    {
        return Application::byApplicant($user->id)
            ->with('jobPosting')
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function normalizeLimit(int $limit): int
    {
        if ($limit < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, self::MAX_LIMIT);
    }
}
