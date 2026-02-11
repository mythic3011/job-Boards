<?php

namespace App\Models;

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
        'cv_file_path',
        'cv_original_name',
        'cv_mime',
        'cv_size_bytes',
        'cv_sha256',
        'status',
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
}
