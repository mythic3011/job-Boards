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
    private const SUSPICIOUS_EVENT_TYPES = [
        'suspicious_user_agent',
        'suspicious_ua_high_risk_path',
        'admin_probe',
        'bot_fingerprint_probe',
        'honeypot.triggered',
        'security.route_probe',
        'security.route_scan_detected',
        'security.unauth_access',
    ];
    private const RECENT_ACTIVITY_EVENT_TYPES = [
        'login_success',
        'login_failed',
        'audit.auth.verify.success',
        'audit.auth.verify.denied',
        'account_locked',
        'audit.auth.locked',
        'audit.application.download_cv.denied',
        'audit.application.approve.denied',
        'audit.application.reject.denied',
        'audit.admin.permission.denied',
        'bot_fingerprint_probe',
        'honeypot.triggered',
        'security.route_probe',
        'security.route_scan_detected',
        'security.unauth_access',
        'user.created',
        'user.deleted',
        'user.locked',
        'user.unlocked',
        'job.created',
        'job.deleted',
        'application.created',
        'application.approved',
        'application.rejected',
        'setup.completed',
    ];

    public function getStats(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_DURATION, function () {
            $today = now()->startOfDay();
            $auditToday = AuditLog::query()->where('occurred_at', '>=', $today);
            $todayDateTime = $today->toDateTimeString();

            $userStats = User::query()
                ->selectRaw('COUNT(*) as total_users')
                ->selectRaw('COALESCE(SUM(CASE WHEN user_type = ? THEN 1 ELSE 0 END), 0) as total_companies', ['company'])
                ->selectRaw('COALESCE(SUM(CASE WHEN user_type = ? THEN 1 ELSE 0 END), 0) as total_individuals', ['individual'])
                ->selectRaw('COALESCE(SUM(CASE WHEN locked_until IS NOT NULL AND locked_until > ? THEN 1 ELSE 0 END), 0) as locked_users', [now()])
                ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_users_today', [$todayDateTime])
                ->first();

            $applicationStats = Application::query()
                ->selectRaw('COUNT(*) as total_applications')
                ->selectRaw("COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_applications")
                ->first();

            $jobStats = JobPosting::query()
                ->selectRaw('COUNT(*) as total_jobs')
                ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_jobs_today', [$todayDateTime])
                ->first();

            return [
                'total_users'        => (int) ($userStats?->total_users ?? 0),
                'total_companies'    => (int) ($userStats?->total_companies ?? 0),
                'total_individuals'  => (int) ($userStats?->total_individuals ?? 0),
                'total_jobs'         => (int) ($jobStats?->total_jobs ?? 0),
                'total_applications' => (int) ($applicationStats?->total_applications ?? 0),
                'pending_applications' => (int) ($applicationStats?->pending_applications ?? 0),
                'locked_users'       => (int) ($userStats?->locked_users ?? 0),
                'new_users_today'    => (int) ($userStats?->new_users_today ?? 0),
                'new_jobs_today'     => (int) ($jobStats?->new_jobs_today ?? 0),
                'events_today'       => (clone $auditToday)->count(),
                'failed_logins_today' => (clone $auditToday)
                    ->whereIn('event_type', ['login_failed', 'audit.auth.verify.denied'])
                    ->count(),
                'suspicious_today'   => (clone $auditToday)
                    ->whereIn('event_type', self::SUSPICIOUS_EVENT_TYPES)
                    ->count(),
            ];
        });
    }

    public function getRecentActivity(): \Illuminate\Support\Collection
    {
        return AuditLog::with('actor')
            ->whereIn('event_type', self::RECENT_ACTIVITY_EVENT_TYPES)
            ->orderByDesc('occurred_at')
            ->limit(8)
            ->get();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
