<?php

use App\Models\JobPosting;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Jobs');

new class extends Component
{
    use WithPagination;

    public function with(): array
    {
        return [
            'jobs' => JobPosting::latest()->paginate(10),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between gap-4 mb-6">
        <h1 class="text-3xl font-bold">Job Listings</h1>

        @if(auth()->check() && auth()->user()->isCompany())
            <x-ui.button href="{{ route('jobs.create') }}" variant="primary">
                Create Job
            </x-ui.button>
        @endif
    </div>

    <div class="space-y-4">
        @forelse($jobs as $job)
            <x-ui.card>
                <h2 class="text-xl font-semibold">
                    <a href="{{ route('jobs.show', $job->idcode) }}" class="text-indigo-700 hover:text-indigo-900">
                        {{ $job->title }}
                    </a>
                </h2>

                <p class="mt-2 text-gray-700">{{ Str::limit($job->requirement, 160) }}</p>

                <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-600">
                    <span>Posted {{ $job->created_at->diffForHumans() }}</span>
                    @if($job->salary)
                        <span class="font-medium text-emerald-700">Salary: {{ $job->salary }}</span>
                    @endif
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty-state message="No job listings yet." />
        @endforelse
    </div>

    <div class="mt-6">
        {{ $jobs->links() }}
    </div>
</div>
