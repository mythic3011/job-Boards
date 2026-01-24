<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\JobPosting;
use App\Policies\ApplicationPolicy;
use App\Policies\JobPostingPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        JobPosting::class => JobPostingPolicy::class,
        Application::class => ApplicationPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Policies are auto-discovered via $policies array, but we can also register manually if needed
        // Gate::policy() calls are redundant when using $policies array

        // Admin gate - check if user has admin role
        Gate::define('admin.access', function ($user) {
            return $user->hasRole('admin');
        });

        RateLimiter::for('file-upload', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Stricter rate limiting for suspicious user agents
        RateLimiter::for('suspicious-ua', function (Request $request) {
            $isHighRisk = str_starts_with($request->path(), '/admin') ||
                         str_starts_with($request->path(), '/install') ||
                         str_starts_with($request->path(), '/login');
            
            if ($isHighRisk) {
                // High-risk paths: 5 requests per 10 minutes
                return Limit::perMinutes(10, 5)->by($request->ip());
            }
            
            // Normal paths: 20 requests per 5 minutes
            return Limit::perMinutes(5, 20)->by($request->ip());
        });
    }
}
