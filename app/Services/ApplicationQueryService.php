<?php

namespace App\Services;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;

class ApplicationQueryService
{
    /**
     * Get applications for a user based on their role.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getApplicationsForUser(User $user, ?string $jobIdcode = null)
    {
        if ($user->isCompany()) {
            return $this->getCompanyApplications($user, $jobIdcode);
        }

        return $this->getIndividualApplications($user);
    }

    /**
     * Get applications for a company user.
     */
    private function getCompanyApplications(User $user, ?string $jobIdcode = null)
    {
        $query = Application::forCompanyJobs($user->id);

        if ($jobIdcode) {
            // OWASP A01: Scope job lookup to company owner
            $job = JobPosting::byIdcode($jobIdcode)
                ->byCompany($user->id)
                ->firstOrFail();
            $query->forJob($job->id);
        }

        return $query->with(['jobPosting', 'applicantUser'])->latest()->get();
    }

    /**
     * Get applications for an individual user.
     */
    private function getIndividualApplications(User $user)
    {
        return Application::byApplicant($user->id)
            ->with('jobPosting')
            ->latest()
            ->get();
    }
}
