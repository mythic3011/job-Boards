<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids, HasRoles, TwoFactorAuthenticatable;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->idcode)) {
                $user->idcode = 'user_' . Str::uuid()->toString();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'idcode',
        'login_id',
        'nickname',
        'email',
        'password',
        'user_type',
        'profile_image_path',
        'locked_until',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locked_until' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'login_id';
    }

    /**
     * Scope a query to only include users by login_id.
     */
    public function scopeByLoginId(Builder $query, string $loginId): Builder
    {
        return $query->where('login_id', $loginId);
    }

    /**
     * Check if user is locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Check if user is a company.
     */
    public function isCompany(): bool
    {
        return $this->user_type === 'company';
    }

    /**
     * Check if user is an individual.
     */
    public function isIndividual(): bool
    {
        return $this->user_type === 'individual';
    }

    /**
     * Get the job postings for this company user.
     */
    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class, 'company_user_id');
    }

    /**
     * Get the applications submitted by this individual user.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'applicant_user_id');
    }
}
