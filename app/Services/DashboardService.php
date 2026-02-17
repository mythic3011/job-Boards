<?php

namespace App\Services;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    /**
     * Cache duration in seconds (5 minutes).
     */
    private const CACHE_DURATION = 300;

    /**
     * Cache key for dashboard stats.
     */
    private const CACHE_KEY = 'dashboard.stats';

    /**
     * Get dashboard statistics.
     *
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_DURATION, function () {
            return [
                'total_users' => User::count(),
                'total_companies' => User::where('user_type', 'company')->count(),
                'total_individuals' => User::where('user_type', 'individual')->count(),
                'total_jobs' => JobPosting::count(),
                'total_applications' => Application::count(),
                'locked_users' => User::whereNotNull('locked_until')
                    ->where('locked_until', '>', now())
                    ->count(),
            ];
        });
    }

    /**
     * Clear dashboard statistics cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}