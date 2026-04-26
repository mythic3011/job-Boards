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
            $userStats = User::query()
                ->selectRaw('COUNT(*) as total_users')
                ->selectRaw("SUM(CASE WHEN user_type = 'company' THEN 1 ELSE 0 END) as total_companies")
                ->selectRaw("SUM(CASE WHEN user_type = 'individual' THEN 1 ELSE 0 END) as total_individuals")
                ->selectRaw('SUM(CASE WHEN locked_until IS NOT NULL AND locked_until > ? THEN 1 ELSE 0 END) as locked_users', [now()])
                ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_users_today', [$today])
                ->first();

            $jobStats = JobPosting::query()
                ->selectRaw('COUNT(*) as total_jobs')
                ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_jobs_today', [$today])
                ->first();

            $applicationStats = Application::query()
                ->selectRaw('COUNT(*) as total_applications')
                ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications")
                ->first();

            $auditStats = AuditLog::query()
                ->where('occurred_at', '>=', $today)
                ->selectRaw('COUNT(*) as events_today')
                ->selectRaw("SUM(CASE WHEN event_type IN ('login_failed', 'audit.auth.verify.denied') THEN 1 ELSE 0 END) as failed_logins_today")
                ->selectRaw('SUM(CASE WHEN event_type IN (\''.implode("','", self::SUSPICIOUS_EVENT_TYPES).'\') THEN 1 ELSE 0 END) as suspicious_today')
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
                'events_today'       => (int) ($auditStats?->events_today ?? 0),
                'failed_logins_today' => (int) ($auditStats?->failed_logins_today ?? 0),
                'suspicious_today'   => (int) ($auditStats?->suspicious_today ?? 0),
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
