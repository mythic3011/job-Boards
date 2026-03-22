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
}; ?>

<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500">Overview of your Jobs Board platform</p>
    </div>

    {{-- Admin Quick Navigation --}}
    <div class="mb-8">
        <x-ui.section-label>Quick Navigation</x-ui.section-label>
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            @can('admin.users.view')
                <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-700 hover:bg-indigo-50 transition-colors">
                    Users
                </a>
            @endcan

            @can('admin.jobs.view')
                <a href="{{ route('admin.jobs.index') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-700 hover:bg-indigo-50 transition-colors">
                    Jobs
                </a>
            @endcan

            @can('admin.applications.view')
                <a href="{{ route('admin.applications.index') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-700 hover:bg-indigo-50 transition-colors">
                    Applications
                </a>
            @endcan

            @can('admin.system.view')
                <a href="{{ route('admin.audit-logs.index') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-700 hover:bg-indigo-50 transition-colors">
                    Audit Logs
                </a>
            @endcan

            @can('admin.settings.view')
                <a href="{{ route('admin.settings.index') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-700 hover:bg-indigo-50 transition-colors">
                    Settings
                </a>
            @endcan
        </div>
    </div>

    {{-- Platform Stats --}}
    <x-ui.section-label>Platform</x-ui.section-label>
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
    <x-ui.section-label>Today</x-ui.section-label>
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

    {{-- Recent Activity --}}
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <x-ui.section-label class="mb-0">Recent Activity</x-ui.section-label>
            @can('admin.system.view')
                <a href="{{ route('admin.audit-logs.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View all audit logs →</a>
            @endcan
        </div>
        <x-ui.card class="divide-y divide-gray-100">
            @forelse($activity as $log)
                <div class="flex items-center justify-between py-2.5 px-1 gap-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $this->getBadgeClass($log->event_type) }} shrink-0">
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
</div>
