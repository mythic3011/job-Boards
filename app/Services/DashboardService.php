<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    private const CACHE_DURATION = 300;
    private const CACHE_KEY = 'dashboard.stats';

    public function getStats(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_DURATION, function () {
            $today = now()->startOfDay();

            $auditToday = AuditLog::where('occurred_at', '>=', $today)
                ->selectRaw("
                    COUNT(*) as total_today,
                    COUNT(CASE WHEN event_type IN ('login_failed', 'audit.auth.verify.denied') THEN 1 END) as failed_logins,
                    COUNT(CASE WHEN event_type IN ('suspicious_user_agent','suspicious_ua_high_risk_path','admin_probe') THEN 1 END) as suspicious
                ")
                ->first();

            return [
                'total_users'        => User::count(),
                'total_companies'    => User::where('user_type', 'company')->count(),
                'total_individuals'  => User::where('user_type', 'individual')->count(),
                'total_jobs'         => JobPosting::count(),
                'total_applications' => Application::count(),
                'pending_applications' => Application::where('status', 'pending')->count(),
                'locked_users'       => User::whereNotNull('locked_until')->where('locked_until', '>', now())->count(),
                'new_users_today'    => User::where('created_at', '>=', $today)->count(),
                'new_jobs_today'     => JobPosting::where('created_at', '>=', $today)->count(),
                'events_today'       => $auditToday?->total_today ?? 0,
                'failed_logins_today' => $auditToday?->failed_logins ?? 0,
                'suspicious_today'   => $auditToday?->suspicious ?? 0,
            ];
        });
    }

    public function getRecentActivity(): \Illuminate\Support\Collection
    {
        return AuditLog::with('actor')
            ->whereIn('event_type', [
                'login_success', 'login_failed', 'audit.auth.verify.success', 'audit.auth.verify.denied', 'account_locked', 'audit.auth.locked',
                'audit.application.download_cv.denied', 'audit.application.approve.denied', 'audit.application.reject.denied',
                'audit.admin.permission.denied',
                'user.created', 'user.deleted', 'user.locked', 'user.unlocked',
                'job.created', 'job.deleted',
                'application.created', 'application.approved', 'application.rejected',
                'setup.completed',
            ])
            ->orderByDesc('occurred_at')
            ->limit(8)
            ->get();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
