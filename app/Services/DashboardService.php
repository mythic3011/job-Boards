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

            return [
                'total_users'        => User::count(),
                'total_companies'    => User::where('user_type', 'company')->count(),
                'total_individuals'  => User::where('user_type', 'individual')->count(),
                'total_jobs'         => JobPosting::count(),
                'total_applications' => Application::count(),
                'pending_applications' => Application::pending()->count(),
                'locked_users'       => User::locked()->count(),
                'new_users_today'    => User::where('created_at', '>=', $today)->count(),
                'new_jobs_today'     => JobPosting::where('created_at', '>=', $today)->count(),
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
