<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Determine if the user can view any applications.
     */
    public function viewAny(User $user): bool
    {
        if ($user->isCompany()) {
            return $user->hasPermissionTo('view own applications');
        }

        if ($user->isIndividual()) {
            return $user->hasPermissionTo('view own submitted applications');
        }

        return false;
    }

    /**
     * Determine if the user can view the application.
     */
    public function view(User $user, Application $application): bool
    {
        if ($user->isCompany()) {
            return $user->hasPermissionTo('view own applications')
                && $this->isApplicationForCompanyJob($application, $user);
        }

        if ($user->isIndividual()) {
            return $user->hasPermissionTo('view own submitted applications')
                && $this->isApplicationByApplicant($application, $user);
        }

        return false;
    }

    /**
     * Determine if the user can create applications.
     */
    public function create(User $user): bool
    {
        return $user->isIndividual() && $user->hasPermissionTo('apply to jobs');
    }

    /**
     * Determine if the user can download the CV.
     */
    public function downloadCv(User $user, Application $application): bool
    {
        if ($user->isCompany()) {
            return $user->hasPermissionTo('download cv')
                && $this->isApplicationForCompanyJob($application, $user);
        }

        if ($user->isIndividual()) {
            // Individuals can download their own CV without explicit permission
            return $this->isApplicationByApplicant($application, $user);
        }

        return false;
    }

    /**
     * Determine if the user can update the application.
     * Applications cannot be changed after submission.
     */
    public function update(User $user, Application $application): bool
    {
        return false;
    }

    /**
     * Determine if the user can delete the application.
     */
    public function delete(User $user, Application $application): bool
    {
        return false;
    }

    /**
     * Check if the application belongs to a job owned by the company user.
     */
    private function isApplicationForCompanyJob(Application $application, User $user): bool
    {
        // Load relationship if not already loaded to avoid N+1 queries
        if (!$application->relationLoaded('jobPosting')) {
            $application->load('jobPosting');
        }

        $jobPosting = $application->jobPosting;

        return $jobPosting && $jobPosting->company_user_id === $user->id;
    }

    /**
     * Check if the application was submitted by the individual user.
     */
    private function isApplicationByApplicant(Application $application, User $user): bool
    {
        return $application->applicant_user_id === $user->id;
    }
}
