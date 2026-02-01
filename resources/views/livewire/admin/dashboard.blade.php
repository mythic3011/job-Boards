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
            'stats' => $dashboardService->getStats(),
        ];
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-3xl font-bold">Admin Dashboard</h1>
        <p class="text-gray-600 mt-1">Overview of your Jobs Board platform</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <x-ui.stat-card
            label="Total Users"
            :value="$stats['total_users']"
            icon-color="text-indigo-600"
        >
            <x-slot:icon>
                <x-heroicon-o-users class="h-12 w-12" />
            </x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card
            label="Companies"
            :value="$stats['total_companies']"
            icon-color="text-green-600"
        >
            <x-slot:icon>
                <x-heroicon-o-building-office class="h-12 w-12" />
            </x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card
            label="Job Seekers"
            :value="$stats['total_individuals']"
            icon-color="text-blue-600"
        >
            <x-slot:icon>
                <x-heroicon-o-user class="h-12 w-12" />
            </x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card
            label="Job Postings"
            :value="$stats['total_jobs']"
            icon-color="text-purple-600"
        >
            <x-slot:icon>
                <x-heroicon-o-briefcase class="h-12 w-12" />
            </x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card
            label="Applications"
            :value="$stats['total_applications']"
            icon-color="text-yellow-600"
        >
            <x-slot:icon>
                <x-heroicon-o-document-text class="h-12 w-12" />
            </x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card
            label="Locked Accounts"
            :value="$stats['locked_users']"
            icon-color="text-red-600"
        >
            <x-slot:icon>
                <x-heroicon-o-lock-closed class="h-12 w-12" />
            </x-slot:icon>
        </x-ui.stat-card>
    </div>

    <!-- Quick Links -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ui.button href="{{ route('admin.users.index') }}" variant="outline" class="h-24 flex-col">
            <x-heroicon-o-users class="h-8 w-8 mb-2" />
            <span>Manage Users</span>
        </x-ui.button>

        <x-ui.button href="{{ route('admin.jobs.index') }}" variant="outline" class="h-24 flex-col">
            <x-heroicon-o-briefcase class="h-8 w-8 mb-2" />
            <span>Manage Jobs</span>
        </x-ui.button>

        <x-ui.button href="{{ route('admin.applications.index') }}" variant="outline" class="h-24 flex-col">
            <x-heroicon-o-document-text class="h-8 w-8 mb-2" />
            <span>View Applications</span>
        </x-ui.button>

        <x-ui.button href="{{ route('admin.settings.index') }}" variant="outline" class="h-24 flex-col">
            <x-heroicon-o-cog-6-tooth class="h-8 w-8 mb-2" />
            <span>Settings</span>
        </x-ui.button>
    </div>
</div>
