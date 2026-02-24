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

    public string $search = '';
    public string $sort = 'latest';
    public bool $showSuggestions = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->showSuggestions = strlen($this->search) >= 2;
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function selectSuggestion(string $title): void
    {
        $this->search = $title;
        $this->showSuggestions = false;
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->showSuggestions = false;
        $this->resetPage();
    }

    public function getSuggestionsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        if (strlen($this->search) < 2) {
            return collect();
        }

        return JobPosting::with('companyUser')
            ->where('title', 'ilike', '%' . $this->search . '%')
            ->latest()
            ->limit(6)
            ->get(['id', 'idcode', 'title', 'salary', 'company_user_id', 'created_at']);
    }

    public function getRecentJobsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return JobPosting::with('companyUser')
            ->latest()
            ->limit(5)
            ->get(['id', 'idcode', 'title', 'company_user_id', 'created_at']);
    }

    public function with(): array
    {
        $cutoff = now()->subHours(48);
        $query = JobPosting::with('companyUser');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'ilike', '%' . $this->search . '%')
                  ->orWhere('requirement', 'ilike', '%' . $this->search . '%');
            });
        }

        match ($this->sort) {
            'salary_desc' => $query->orderByRaw('salary DESC NULLS LAST'),
            'oldest'      => $query->oldest(),
            default       => $query->latest(),
        };

        return [
            'jobs'   => $query->paginate(10),
            'cutoff' => $cutoff,
        ];
    }
}; ?>

<div>
    {{-- Page header --}}
    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Job Listings</h1>
            <p class="text-sm text-gray-500 mt-1 mb-1">{{ $jobs->total() }} {{ Str::plural('opportunity', $jobs->total()) }} available</p>
        </div>

        @if(auth()->check() && auth()->user()->isCompany())
            <x-ui.button href="{{ route('jobs.create') }}" variant="primary">
                Post a Job
            </x-ui.button>
        @endif
    </div>

    {{-- Search + sort bar --}}
    <div class="flex gap-3 mb-6" x-data="{
        acIndex: -1,
        get acCount() { return document.querySelectorAll('[data-ac-item]').length },
        moveDown() { if (this.acCount) this.acIndex = (this.acIndex + 1) % this.acCount },
        moveUp()   { if (this.acCount) this.acIndex = (this.acIndex - 1 + this.acCount) % this.acCount },
        selectCurrent() { if (this.acIndex >= 0) this.$refs['ac_' + this.acIndex]?.click() },
        reset() { this.acIndex = -1 }
    }"
        @keydown.arrow-down.prevent="moveDown()"
        @keydown.arrow-up.prevent="moveUp()"
        @keydown.enter.prevent="selectCurrent()"
    >
        <div class="relative flex-1">
            <div
                class="flex items-center gap-3 rounded-lg border bg-white px-4 py-2.5 shadow-sm transition-all duration-150"
                :class="$wire.showSuggestions ? 'border-indigo-500 ring-2 ring-indigo-100 rounded-b-none' : 'border-gray-300'"
            >
                <svg style="width:18px;height:18px;flex-shrink:0;color:#9ca3af" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>

                <input
                    type="text"
                    wire:model.live.debounce.250ms="search"
                    placeholder="Search by title, skills, or requirements…"
                    class="flex-1 bg-transparent text-sm text-gray-800 placeholder-gray-400 outline-none min-w-0"
                    autocomplete="off"
                    @focus="$wire.set('showSuggestions', $wire.search.length >= 2)"
                    @blur="setTimeout(() => { $wire.set('showSuggestions', false); reset() }, 160)"
                    @keydown.escape="$wire.clearSearch(); reset()"
                />

                @if($search)
                    <button wire:click="clearSearch" @click="reset()" class="shrink-0 rounded-full p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors" aria-label="Clear search">
                        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Autocomplete dropdown --}}
            @if($showSuggestions && $this->suggestions->isNotEmpty())
                <div class="absolute left-0 right-0 top-full z-50 overflow-hidden rounded-b-lg border border-t-0 border-indigo-500 bg-white shadow-lg" wire:key="suggestions-dropdown">
                    @foreach($this->suggestions as $i => $suggestion)
                        <button
                            x-ref="ac_{{ $i }}"
                            data-ac-item
                            wire:click="selectSuggestion('{{ addslashes($suggestion->title) }}')"
                            @click="reset()"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors focus:outline-none border-b border-gray-50 last:border-0"
                            :class="acIndex === {{ $i }} ? 'bg-indigo-50' : 'hover:bg-gray-50'"
                        >
                            <div class="flex shrink-0 items-center justify-center rounded bg-indigo-100 font-bold text-indigo-700 uppercase" style="width:32px;height:32px;font-size:11px">
                                {{ Str::substr($suggestion->companyUser?->nickname ?? '?', 0, 2) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-gray-800">
                                    {!! preg_replace('/(' . preg_quote($search, '/') . ')/i', '<mark class="bg-yellow-100 text-yellow-900 rounded-sm px-0.5 not-italic font-semibold">$1</mark>', e($suggestion->title)) !!}
                                </div>
                                <div class="truncate text-xs text-gray-500 mt-0.5">
                                    {{ $suggestion->companyUser?->nickname ?? 'Unknown company' }}
                                    @if($suggestion->salary)
                                        <span class="mx-1 text-gray-300">&middot;</span>
                                        <span class="text-emerald-600 font-medium">{{ $suggestion->salary }}</span>
                                    @endif
                                </div>
                            </div>
                            <svg style="width:14px;height:14px;flex-shrink:0;color:#d1d5db" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                            </svg>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <select wire:model.live="sort" class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all duration-150">
            <option value="latest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="salary_desc">Highest salary</option>
        </select>
    </div>

    {{-- Active search label --}}
    @if($search)
        <div class="flex items-center gap-2 mb-4">
            <span class="text-sm text-gray-500">
                @if($jobs->total() === 0)
                    No results for
                @else
                    {{ $jobs->total() }} {{ Str::plural('result', $jobs->total()) }} for
                @endif
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 border border-indigo-100 px-3 py-0.5 text-sm font-medium text-indigo-700">
                "{{ $search }}"
                <button wire:click="clearSearch" class="hover:text-indigo-900 transition-colors">
                    <svg style="width:12px;height:12px" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
        </div>
    @endif

    {{-- Two-column LinkedIn-style layout --}}
    <div class="flex gap-6 items-start">

        {{-- Main job feed --}}
        <div class="min-w-0 flex-1">
            <div class="space-y-3">
                @forelse($jobs as $job)
                    <div class="group rounded-lg border border-gray-200 bg-white px-5 py-4 shadow-sm hover:border-indigo-200 hover:shadow-md transition-all duration-150">
                        <div class="flex items-start gap-4">

                            {{-- Company logo placeholder --}}
                            <div class="flex shrink-0 items-center justify-center rounded-md border border-gray-100 bg-gray-50 font-bold text-gray-600 uppercase transition-colors group-hover:border-indigo-100 group-hover:bg-indigo-50 group-hover:text-indigo-700" style="width:48px;height:48px;font-size:13px">
                                {{ Str::substr($job->companyUser?->nickname ?? '?', 0, 2) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-base font-semibold leading-snug">
                                        <a href="{{ route('jobs.show', $job->idcode) }}" class="text-gray-900 hover:text-indigo-700 transition-colors">
                                            {{ $job->title }}
                                        </a>
                                    </h2>
                                    @if($job->created_at->gt($cutoff))
                                        <span class="inline-flex shrink-0 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-xs font-semibold text-emerald-700">New</span>
                                    @endif
                                </div>

                                {{-- Company + salary --}}
                                <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                    <p class="text-sm text-gray-500">{{ $job->companyUser?->nickname ?? 'Unknown company' }}</p>
                                    @if($job->salary)
                                        <span class="text-gray-300">&middot;</span>
                                        <span class="text-sm font-semibold text-emerald-700">HKD{{ $job->salary }}</span>
                                    @endif
                                </div>

                                {{-- Requirement snippet --}}
                                <p class="mt-2 text-sm text-gray-600 leading-relaxed line-clamp-2">
                                    {{ $job->requirement }}
                                </p>

                                {{-- Footer meta --}}
                                <div class="mt-3 flex items-center justify-between gap-4">
                                    <span class="flex items-center gap-1.5 text-xs text-gray-400">
                                        <svg style="width:13px;height:13px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        {{ $job->created_at->diffForHumans() }}
                                    </span>

                                    <a href="{{ route('jobs.show', $job->idcode) }}" class="shrink-0 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline underline-offset-2 transition-colors">
                                        View details &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state
                        title="{{ $search ? 'No matching jobs found' : 'No job listings yet' }}"
                        message="{{ $search ? 'Try a different search term or browse all listings.' : 'Check back soon or, if you\'re hiring, post the first opportunity.' }}"
                    >
                        <x-slot:action>
                            <div class="flex flex-wrap items-center justify-center gap-3">
                                @if($search)
                                    <x-ui.button wire:click="clearSearch" variant="outline">
                                        Clear search
                                    </x-ui.button>
                                @else
                                    @auth
                                        @if(auth()->user()->isCompany())
                                            <x-ui.button href="{{ route('jobs.create') }}" variant="primary">
                                                Create a job
                                            </x-ui.button>
                                        @else
                                            <x-ui.button href="{{ route('register') }}" variant="primary">
                                                register to apply
                                            </x-ui.button>
                                        @endif
                                    @else
                                        <x-ui.button href="{{ route('register') }}" variant="primary">
                                            Sign up to post jobs
                                        </x-ui.button>
                                        <x-ui.button href="{{ route('login') }}" variant="outline">
                                            Log in
                                        </x-ui.button>
                                    @endauth
                                @endif
                            </div>
                        </x-slot:action>
                    </x-ui.empty-state>
                @endforelse
            </div>

            <div class="mt-6">
                {{ $jobs->links() }}
            </div>
        </div>

        {{-- Right sidebar (hidden on mobile) --}}
        <aside class="hidden lg:block w-72 shrink-0 space-y-4">

            {{-- Stats --}}
            <div class="rounded-lg border border-gray-200 bg-white px-5 py-4 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Overview</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Total listings</span>
                        <span class="text-sm font-semibold text-gray-900">{{ $jobs->total() }}</span>
                    </div>
                    <div class="border-t border-gray-100"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">This page</span>
                        <span class="text-sm font-semibold text-gray-900">
                            {{ $jobs->firstItem() ?? 0 }}–{{ $jobs->lastItem() ?? 0 }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Recently posted --}}
            @if($this->recentJobs->isNotEmpty())
                <div class="rounded-lg border border-gray-200 bg-white px-5 py-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Recently posted</h3>
                    <div class="space-y-3">
                        @foreach($this->recentJobs as $recent)
                            <a href="{{ route('jobs.show', $recent->idcode) }}" class="flex items-start gap-2.5 group">
                                <div class="flex shrink-0 items-center justify-center rounded font-bold text-gray-500 uppercase bg-gray-100 group-hover:bg-indigo-100 group-hover:text-indigo-700 transition-colors" style="width:28px;height:28px;font-size:10px">
                                    {{ Str::substr($recent->companyUser?->nickname ?? '?', 0, 2) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-medium text-gray-700 group-hover:text-indigo-700 transition-colors">{{ $recent->title }}</p>
                                    <p class="text-xs text-gray-400">{{ $recent->created_at->diffForHumans() }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- CTA for companies --}}
            @auth
                @if(auth()->user()->isCompany())
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-5 py-4">
                        <h3 class="text-sm font-semibold text-indigo-800 mb-1">Hiring?</h3>
                        <p class="text-xs text-indigo-600 mb-3 leading-relaxed">Post a job and reach qualified candidates today.</p>
                        <x-ui.button href="{{ route('jobs.create') }}" variant="primary" size="sm">
                            Post a Job
                        </x-ui.button>
                    </div>
                @endif
            @else
                <div class="rounded-lg border border-gray-200 bg-white px-5 py-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Get started</h3>
                    <p class="text-xs text-gray-500 mb-3 leading-relaxed">Sign up to apply for jobs or post opportunities.</p>
                    <div class="space-y-2">
                        <x-ui.button href="{{ route('register') }}" variant="primary" size="sm" class="w-full">
                            Sign up
                        </x-ui.button>
                        <x-ui.button href="{{ route('login') }}" variant="outline" size="sm" class="w-full">
                            Log in
                        </x-ui.button>
                    </div>
                </div>
            @endauth

        </aside>
    </div>
</div>
