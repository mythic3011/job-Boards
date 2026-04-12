<?php

use App\Services\DashboardService;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;
use function Livewire\Volt\title;

layout('layouts.app');
title('Admin Dashboard');

new class extends Component
{
    public function with(DashboardService $dashboardService): array
    {
        return [
            'stats' => $dashboardService->getStats(),
            'activity' => $dashboardService->getRecentActivity(),
        ];
    }

    public function getBadgeClass(string $eventType): string
    {
        return match (true) {
            str_contains($eventType, 'failed') || str_contains($eventType, 'locked') => 'bg-red-100 text-red-700',
            str_contains($eventType, 'suspicious') || str_contains($eventType, 'probe') => 'bg-orange-100 text-orange-700',
            str_contains($eventType, 'login') => 'bg-green-100 text-green-700',
            str_contains($eventType, 'setup') => 'bg-indigo-100 text-indigo-700',
            default => 'bg-gray-100 text-gray-600',
        };
    }

    public function getActivityLabel(string $eventType): string
    {
        return match ($eventType) {
            'login_success' => 'Successful sign-in',
            'login_failed' => 'Failed sign-in',
            'account_locked', 'user.locked' => 'Account locked',
            'user.unlocked' => 'Account unlocked',
            'user.created' => 'User created',
            'user.deleted' => 'User deleted',
            'job.created' => 'Job published',
            'job.deleted' => 'Job deleted',
            'application.created' => 'Application submitted',
            'application.approved' => 'Application approved',
            'application.rejected' => 'Application rejected',
            'setup.completed' => 'Setup completed',
            default => ucwords(str_replace(['_', '.'], ' ', $eventType)),
        };
    }
}; ?>

@php
    $commandCenter = [
        [
            'can' => auth()->user()?->can('admin.users.view'),
            'href' => route('admin.users.index'),
            'label' => 'Users',
            'description' => 'Review accounts and moderation status.',
            'value' => number_format($stats['total_users']),
            'iconBg' => 'bg-indigo-50 text-indigo-600',
            'icon' => 'users',
        ],
        [
            'can' => auth()->user()?->can('admin.jobs.view'),
            'href' => route('admin.jobs.index'),
            'label' => 'Jobs',
            'description' => 'Check published roles and draft hygiene.',
            'value' => number_format($stats['total_jobs']),
            'iconBg' => 'bg-violet-50 text-violet-600',
            'icon' => 'briefcase',
        ],
        [
            'can' => auth()->user()?->can('admin.applications.view'),
            'href' => route('admin.applications.index'),
            'label' => 'Applications',
            'description' => 'Process inbound candidates and review queue.',
            'value' => number_format($stats['pending_applications']),
            'valueLabel' => 'pending',
            'iconBg' => 'bg-amber-50 text-amber-600',
            'icon' => 'document',
        ],
        [
            'can' => auth()->user()?->can('admin.system.view'),
            'href' => route('admin.audit-logs.index'),
            'label' => 'Audit Logs',
            'description' => 'Inspect sign-in and system activity.',
            'value' => number_format($stats['events_today']),
            'valueLabel' => 'today',
            'iconBg' => 'bg-slate-100 text-slate-600',
            'icon' => 'chart',
        ],
        [
            'can' => auth()->user()?->can('admin.settings.view'),
            'href' => route('admin.settings.index'),
            'label' => 'Settings',
            'description' => 'Adjust platform controls and admin defaults.',
            'value' => 'Admin',
            'valueLabel' => 'controls',
            'iconBg' => 'bg-emerald-50 text-emerald-600',
            'icon' => 'cog',
        ],
    ];

    $securityPulse = [
        [
            'label' => 'Failed Sign-ins Today',
            'value' => $stats['failed_logins_today'],
            'tone' => $stats['failed_logins_today'] > 0 ? 'text-red-700' : 'text-green-700',
            'description' => $stats['failed_logins_today'] > 0 ? 'Review auth noise and verify it is expected.' : 'No failed sign-ins recorded today.',
        ],
        [
            'label' => 'Suspicious Events',
            'value' => $stats['suspicious_today'],
            'tone' => $stats['suspicious_today'] > 0 ? 'text-orange-700' : 'text-green-700',
            'description' => $stats['suspicious_today'] > 0 ? 'Inspect anti-probe and suspicious-user-agent events.' : 'No suspicious events recorded today.',
        ],
        [
            'label' => 'Locked Accounts',
            'value' => $stats['locked_users'],
            'tone' => $stats['locked_users'] > 0 ? 'text-amber-700' : 'theme-text-strong',
            'description' => $stats['locked_users'] > 0 ? 'Some accounts are currently locked and may need review.' : 'No accounts are currently locked.',
        ],
    ];
@endphp

<div class="space-y-8">
    <div class="rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 px-6 py-7 text-white shadow-xl shadow-slate-900/10 sm:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-200/80">Admin Dashboard</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">Operational snapshot</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300">
                    Review pending applications, watch authentication risk, and jump straight into the admin surfaces that need action.
                </p>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:min-w-[540px]">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur-sm">
                    <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Pending Review</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['pending_applications']) }}</p>
                    <p class="mt-2 text-sm text-slate-300">Applications waiting on a decision.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur-sm">
                    <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Failed Sign-ins Today</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['failed_logins_today']) }}</p>
                    <p class="mt-2 text-sm text-slate-300">Authentication failures captured since midnight.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur-sm">
                    <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Suspicious Events</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['suspicious_today']) }}</p>
                    <p class="mt-2 text-sm text-slate-300">Signals worth checking in the audit stream.</p>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="mb-4 flex items-end justify-between gap-4">
            <div>
                <x-ui.section-label class="mb-2">Command Center</x-ui.section-label>
                <p class="theme-text-muted text-sm">Move into the admin surface that maps to the current workload.</p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach($commandCenter as $item)
                @if($item['can'])
                    <a href="{{ $item['href'] }}" class="theme-panel group rounded-2xl border p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-[var(--app-accent-soft-border)] hover:shadow-md">
                        <div class="flex items-start justify-between gap-3">
                            <span class="theme-panel-subtle inline-flex h-11 w-11 items-center justify-center rounded-xl border {{ $item['iconBg'] }}">
                                @if($item['icon'] === 'users')
                                    <x-heroicon-o-users class="h-5 w-5" />
                                @elseif($item['icon'] === 'briefcase')
                                    <x-heroicon-o-briefcase class="h-5 w-5" />
                                @elseif($item['icon'] === 'document')
                                    <x-heroicon-o-document-text class="h-5 w-5" />
                                @elseif($item['icon'] === 'chart')
                                    <x-heroicon-o-chart-bar class="h-5 w-5" />
                                @else
                                    <x-heroicon-o-cog-6-tooth class="h-5 w-5" />
                                @endif
                            </span>
                            <span class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em] group-hover:text-[var(--app-link-accent)]">Open</span>
                        </div>
                        <div class="mt-4">
                            <p class="theme-text-strong text-base font-semibold">{{ $item['label'] }}</p>
                            <p class="theme-text-muted mt-1 text-sm leading-5">{{ $item['description'] }}</p>
                        </div>
                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="theme-text-strong text-2xl font-semibold">{{ $item['value'] }}</span>
                            @if(isset($item['valueLabel']))
                                <span class="theme-text-muted text-xs font-medium uppercase tracking-[0.14em]">{{ $item['valueLabel'] }}</span>
                            @endif
                        </div>
                    </a>
                @endif
            @endforeach
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.8fr)_minmax(320px,1fr)]">
        <div class="space-y-6">
            <div>
                <div class="mb-4 flex items-end justify-between gap-4">
                    <div>
                        <x-ui.section-label class="mb-2">Platform Snapshot</x-ui.section-label>
                        <p class="theme-text-muted text-sm">Core population and marketplace counts across the platform.</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                    <x-ui.stat-card label="Total Users" :value="$stats['total_users']" icon-color="text-indigo-600">
                        <x-slot:icon><x-heroicon-o-users class="h-12 w-12" /></x-slot:icon>
                    </x-ui.stat-card>

                    <x-ui.stat-card label="Companies" :value="$stats['total_companies']" icon-color="text-green-600">
                        <x-slot:icon><x-heroicon-o-building-office class="h-12 w-12" /></x-slot:icon>
                    </x-ui.stat-card>

                    <x-ui.stat-card label="Job Seekers" :value="$stats['total_individuals']" icon-color="text-blue-600">
                        <x-slot:icon><x-heroicon-o-user class="h-12 w-12" /></x-slot:icon>
                    </x-ui.stat-card>

                    <x-ui.stat-card label="Job Postings" :value="$stats['total_jobs']" icon-color="text-purple-600">
                        <x-slot:icon><x-heroicon-o-briefcase class="h-12 w-12" /></x-slot:icon>
                    </x-ui.stat-card>

                    <x-ui.stat-card label="Applications" :value="$stats['total_applications']" icon-color="text-yellow-600">
                        <x-slot:icon><x-heroicon-o-document-text class="h-12 w-12" /></x-slot:icon>
                    </x-ui.stat-card>

                    <x-ui.stat-card label="New Users Today" :value="$stats['new_users_today']" icon-color="text-sky-600">
                        <x-slot:icon><x-heroicon-o-user-plus class="h-12 w-12" /></x-slot:icon>
                    </x-ui.stat-card>
                </div>
            </div>

            <div>
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <x-ui.section-label class="mb-2">Recent Activity</x-ui.section-label>
                        <p class="theme-text-muted text-sm">Latest events that changed the platform state.</p>
                    </div>
                    @can('admin.system.view')
                        <a href="{{ route('admin.audit-logs.index') }}" class="theme-link text-xs font-medium">View all audit logs →</a>
                    @endcan
                </div>
                <x-ui.card class="divide-y" style="border-color: var(--app-panel-border);">
                    @forelse($activity as $log)
                        <div class="flex items-start justify-between gap-4 px-1 py-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $this->getBadgeClass($log->event_type) }} shrink-0">
                                        {{ $this->getActivityLabel($log->event_type) }}
                                    </span>
                                    @if($log->ip)
                                        <span class="theme-text-muted truncate font-mono text-[11px]">{{ $log->ip }}</span>
                                    @endif
                                </div>
                                <p class="theme-text-strong mt-2 text-sm">
                                    {{ $log->actor?->nickname ?? $log->actor_type ?? 'guest' }}
                                </p>
                            </div>
                            <span class="theme-text-muted shrink-0 text-xs" title="{{ $log->occurred_at->toDateTimeString() }}">
                                {{ $log->occurred_at->diffForHumans() }}
                            </span>
                        </div>
                    @empty
                        <p class="theme-text-muted py-8 text-center text-sm">No recent activity.</p>
                    @endforelse
                </x-ui.card>
            </div>
        </div>

        <div class="space-y-6">
            <div>
                <div class="mb-4">
                    <x-ui.section-label class="mb-2">Security Pulse</x-ui.section-label>
                    <p class="theme-text-muted text-sm">Auth and platform risk indicators from today’s audit stream.</p>
                </div>
                <x-ui.card class="space-y-4">
                    @foreach($securityPulse as $item)
                        <div class="theme-panel-subtle rounded-2xl border px-4 py-4">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="theme-text-strong text-sm font-medium">{{ $item['label'] }}</p>
                                <p class="text-2xl font-semibold {{ $item['tone'] }}">{{ number_format($item['value']) }}</p>
                            </div>
                            <p class="theme-text-muted mt-2 text-sm">{{ $item['description'] }}</p>
                        </div>
                    @endforeach
                </x-ui.card>
            </div>

            <div>
                <div class="mb-4">
                    <x-ui.section-label class="mb-2">Today</x-ui.section-label>
                    <p class="theme-text-muted text-sm">Daily operational movement across users, jobs, and audits.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-1">
                    <x-ui.stat-card label="New Jobs Today" :value="$stats['new_jobs_today']" icon-color="text-purple-500">
                        <x-slot:icon><x-heroicon-o-plus-circle class="h-12 w-12" /></x-slot:icon>
                    </x-ui.stat-card>

                    <x-ui.stat-card label="Audit Events Today" :value="$stats['events_today']" icon-color="text-gray-500">
                        <x-slot:icon><x-heroicon-o-chart-bar class="h-12 w-12" /></x-slot:icon>
                    </x-ui.stat-card>
                </div>
            </div>
        </div>
    </div>
</div>
