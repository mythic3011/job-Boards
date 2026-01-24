<?php

namespace App\Policies;

use App\Models\JobPosting;
use App\Models\User;

class JobPostingPolicy
{
    /**
     * Determine if the user can view any job postings.
     * Job listings are public and viewable by anyone.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the job posting.
     * Job postings are public and viewable by anyone.
     */
    public function view(?User $user, JobPosting $jobPosting): bool
    {
        return true;
    }

    /**
     * Determine if the user can create job postings.
     */
    public function create(User $user): bool
    {
        return $user->isCompany() && $user->hasPermissionTo('create jobs');
    }

    /**
     * Determine if the user can update the job posting.
     */
    public function update(User $user, JobPosting $jobPosting): bool
    {
        return $user->isCompany()
            && $user->hasPermissionTo('update own jobs')
            && $this->isJobOwnedByCompany($jobPosting, $user);
    }

    /**
     * Determine if the user can delete the job posting.
     */
    public function delete(User $user, JobPosting $jobPosting): bool
    {
        return $user->isCompany()
            && $user->hasPermissionTo('delete own jobs')
            && $this->isJobOwnedByCompany($jobPosting, $user);
    }

    /**
     * Determine if the user can view applications for this job posting.
     */
    public function viewApplications(User $user, JobPosting $jobPosting): bool
    {
        return $user->isCompany()
            && $user->hasPermissionTo('view own applications')
            && $this->isJobOwnedByCompany($jobPosting, $user);
    }

    /**
     * Check if the job posting is owned by the company user.
     */
    private function isJobOwnedByCompany(JobPosting $jobPosting, User $user): bool
    {
        return $jobPosting->company_user_id === $user->id;
    }
}
