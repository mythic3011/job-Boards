<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JobPosting extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'job_postings';

    protected $fillable = [
        'idcode',
        'company_user_id',
        'title',
        'requirement',
        'duty',
        'salary_from',
        'salary_to',
    ];

    /**
     * Display-friendly salary string, e.g. "$50,000 - $70,000" or "$50,000".
     */
    public function getSalaryAttribute(): ?string
    {
        if (!$this->salary_from) {
            return null;
        }
        if ($this->salary_to) {
            return '$' . number_format($this->salary_from) . ' - $' . number_format($this->salary_to);
        }
        return '$' . number_format($this->salary_from);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($jobPosting) {
            if (empty($jobPosting->idcode)) {
                $jobPosting->idcode = 'job_' . Str::uuid()->toString();
            }
        });
    }

    /**
     * Scope a query to only include job postings by idcode.
     */
    public function scopeByIdcode(Builder $query, string $idcode): Builder
    {
        return $query->where('idcode', $idcode);
    }

    /**
     * Scope a query to only include job postings owned by a company.
     */
    public function scopeByCompany(Builder $query, string $companyUserId): Builder
    {
        return $query->where('company_user_id', $companyUserId);
    }

    /**
     * Get the company user that owns this job posting.
     */
    public function companyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_user_id');
    }

    /**
     * Get the applications for this job posting.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'job_id');
    }
}
