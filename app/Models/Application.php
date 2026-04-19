<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Application extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'idcode',
        'job_id',
        'applicant_user_id',
        'message',
        'decision_message',
        'decision_message_read_at',
        'cv_file_path',
        'cv_original_name',
        'cv_mime',
        'cv_size_bytes',
        'cv_sha256',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($application) {
            if (empty($application->idcode)) {
                $application->idcode = 'app_' . Str::uuid()->toString();
            }
        });
    }

    /**
     * Scope a query to only include applications by idcode.
     */
    public function scopeByIdcode(Builder $query, string $idcode): Builder
    {
        return $query->where('idcode', $idcode);
    }

    /**
     * Scope a query to only include applications for a specific job.
     */
    public function scopeForJob(Builder $query, string $jobId): Builder
    {
        return $query->where('job_id', $jobId);
    }

    /**
     * Scope a query to only include applications by a specific applicant.
     */
    public function scopeByApplicant(Builder $query, string $applicantUserId): Builder
    {
        return $query->where('applicant_user_id', $applicantUserId);
    }

    /**
     * Scope a query to applications with a specific status.
     */
    public function scopeByStatus(Builder $query, string|ApplicationStatus $status): Builder
    {
        $resolvedStatus = $status instanceof ApplicationStatus
            ? $status
            : ApplicationStatus::from($status);

        return $query->where('status', $resolvedStatus->value);
    }

    /**
     * Scope a query to pending applications.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->byStatus(ApplicationStatus::PENDING);
    }

    /**
     * Scope a query to approved applications.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->byStatus(ApplicationStatus::APPROVED);
    }

    /**
     * Scope a query to rejected applications.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->byStatus(ApplicationStatus::REJECTED);
    }

    /**
     * Scope a query to only include applications for jobs owned by a company.
     */
    public function scopeForCompanyJobs(Builder $query, string $companyUserId): Builder
    {
        return $query->whereHas('jobPosting', function ($q) use ($companyUserId) {
            $q->where('company_user_id', $companyUserId);
        });
    }

    /**
     * Get the job posting this application is for.
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_id');
    }

    /**
     * Get the applicant user.
     */
    public function applicantUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    /**
     * Set the status attribute with validation.
     */
    public function setStatusAttribute($value): void
    {
        // Business transition rules live here only. Do not duplicate them in
        // model hooks, observers, or other hidden lifecycle gates.
        // Convert string to enum if needed
        $status = $value instanceof ApplicationStatus ? $value : ApplicationStatus::from($value);

        // Validate transition if status already exists and is different from new status
        if (isset($this->attributes['status']) && !empty($this->attributes['status'])) {
            $currentStatus = ApplicationStatus::from($this->attributes['status']);

            // Only validate transition if status is actually changing
            if ($currentStatus->value !== $status->value && !$currentStatus->canTransitionTo($status)) {
                throw new \InvalidArgumentException(
                    "Cannot transition application status from {$currentStatus->value} to {$status->value}"
                );
            }
        }

        $this->attributes['status'] = $status->value;
    }

    /**
     * Get the status attribute as enum.
     */
    public function getStatusAttribute($value): ApplicationStatus
    {
        return ApplicationStatus::from($value);
    }
}
