<?php

use App\Services\DashboardService;
use Livewire\Volt\Component;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Admin Dashboard');

new class extends Component
{
    public function with(DashboardService $dashboardService): array
    {
        return [
            'stats'    => $dashboardService->getStats(),
            'activity' => $dashboardService->getRecentActivity(),
        ];
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-3xl font-bold">Admin Dashboard</h1>
        <p class="text-gray-600 mt-1">Overview of your Jobs Board platform</p>
    </div>

    {{-- Platform Stats --}}
    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Platform</p>
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
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

        <x-ui.stat-card label="Pending Applications" :value="$stats['pending_applications']" icon-color="text-orange-500">
            <x-slot:icon><x-heroicon-o-clock class="h-12 w-12" /></x-slot:icon>
        </x-ui.stat-card>
    </div>

    {{-- Today Stats --}}
    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Today</p>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <x-ui.stat-card label="New Users" :value="$stats['new_users_today']" icon-color="text-indigo-500">
            <x-slot:icon><x-heroicon-o-user-plus class="h-12 w-12" /></x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card label="New Jobs" :value="$stats['new_jobs_today']" icon-color="text-purple-500">
            <x-slot:icon><x-heroicon-o-plus-circle class="h-12 w-12" /></x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card label="Audit Events" :value="$stats['events_today']" icon-color="text-gray-500">
            <x-slot:icon><x-heroicon-o-chart-bar class="h-12 w-12" /></x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card label="Failed Logins" :value="$stats['failed_logins_today']" icon-color="text-red-500">
            <x-slot:icon><x-heroicon-o-exclamation-triangle class="h-12 w-12" /></x-slot:icon>
        </x-ui.stat-card>
    </div>

    {{-- Bottom: Recent Activity + Quick Links --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Recent Activity --}}
        <div class="lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Recent Activity</p>
                @can('admin.system.view')
                    <a href="{{ route('admin.audit-logs.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View all audit logs →</a>
                @endcan
            </div>
            <x-ui.card class="divide-y divide-gray-100">
                @forelse($activity as $log)
                    @php
                        $badgeClass = match(true) {
                            str_contains($log->event_type, 'failed') || str_contains($log->event_type, 'locked') => 'bg-red-100 text-red-700',
                            str_contains($log->event_type, 'suspicious') || str_contains($log->event_type, 'probe') => 'bg-orange-100 text-orange-700',
                            str_contains($log->event_type, 'login') => 'bg-green-100 text-green-700',
                            str_contains($log->event_type, 'setup') => 'bg-indigo-100 text-indigo-700',
                            default => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <div class="flex items-center justify-between py-2.5 px-1 gap-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeClass }} shrink-0">
                            {{ str_replace('_', ' ', $log->event_type) }}
                        </span>
                        <span class="text-sm text-gray-700 truncate flex-1">
                            {{ $log->actor?->nickname ?? $log->actor_type ?? 'guest' }}
                            @if($log->ip)
                                <span class="text-gray-400 font-mono text-xs ml-1">{{ $log->ip }}</span>
                            @endif
                        </span>
                        <span class="text-xs text-gray-400 shrink-0" title="{{ $log->occurred_at->toDateTimeString() }}">
                            {{ $log->occurred_at->diffForHumans() }}
                        </span>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">No recent activity.</p>
                @endforelse
            </x-ui.card>
        </div>

        {{-- Quick Links --}}
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Quick Access</p>
            <div class="grid grid-cols-2 gap-3">
                @can('admin.users.view')
                    <a href="{{ route('admin.users.index') }}" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white p-4 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                        <x-heroicon-o-users class="h-7 w-7" />
                        Users
                    </a>
                @endcan
                @can('admin.jobs.view')
                    <a href="{{ route('admin.jobs.index') }}" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white p-4 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                        <x-heroicon-o-briefcase class="h-7 w-7" />
                        Jobs
                    </a>
                @endcan
                @can('admin.applications.view')
                    <a href="{{ route('admin.applications.index') }}" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white p-4 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                        <x-heroicon-o-document-text class="h-7 w-7" />
                        Applications
                    </a>
                @endcan
                @can('admin.system.view')
                    <a href="{{ route('admin.audit-logs.index') }}" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white p-4 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                        <x-heroicon-o-shield-check class="h-7 w-7" />
                        Audit Logs
                    </a>
                @endcan
                @can('admin.settings.view')
                    <a href="{{ route('admin.settings.index') }}" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white p-4 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 transition-colors col-span-2">
                        <x-heroicon-o-cog-6-tooth class="h-7 w-7" />
                        Settings
                    </a>
                @endcan
            </div>
        </div>

    </div>
</div>
