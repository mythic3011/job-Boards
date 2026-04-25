<?php

use App\Models\JobPosting;
use Illuminate\Support\Facades\DB;
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
    public int $highlightedSuggestion = -1;

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->highlightedSuggestion = -1;
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
        $this->highlightedSuggestion = -1;
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->showSuggestions = false;
        $this->highlightedSuggestion = -1;
        $this->resetPage();
    }

    public function showSuggestionsIfEligible(): void
    {
        $this->showSuggestions = strlen($this->search) >= 2;
    }

    public function hideSuggestions(): void
    {
        $this->showSuggestions = false;
        $this->highlightedSuggestion = -1;
    }

    public function moveSuggestionDown(): void
    {
        $count = $this->getSuggestionsProperty()->count();

        if ($count === 0) {
            $this->highlightedSuggestion = -1;

            return;
        }

        $this->highlightedSuggestion = ($this->highlightedSuggestion + 1) % $count;
    }

    public function moveSuggestionUp(): void
    {
        $count = $this->getSuggestionsProperty()->count();

        if ($count === 0) {
            $this->highlightedSuggestion = -1;

            return;
        }

        $this->highlightedSuggestion = ($this->highlightedSuggestion - 1 + $count) % $count;
    }

    public function selectHighlightedSuggestion(): void
    {
        $suggestion = $this->getSuggestionsProperty()
            ->values()
            ->get($this->highlightedSuggestion);

        if (! $suggestion) {
            return;
        }

        $this->selectSuggestion($suggestion->title);
    }

    public function getSuggestionsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        if (strlen($this->search) < 2) {
            return collect();
        }

        $like = $this->likeOperator();

        return JobPosting::with('companyUser')
            ->where('title', $like, '%' . $this->search . '%')
            ->latest()
            ->limit(6)
            ->get(['id', 'idcode', 'title', 'salary_from', 'salary_to', 'company_user_id', 'created_at']);
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
        $like = $this->likeOperator();

        if ($this->search) {
            $query->where(function ($q) use ($like) {
                $q->where('title', $like, '%' . $this->search . '%')
                  ->orWhere('requirement', $like, '%' . $this->search . '%');
            });
        }

        match ($this->sort) {
            'salary_desc' => $query->orderBy('salary_from', 'desc'),
            'oldest'      => $query->oldest(),
            default       => $query->latest(),
        };

        return [
            'jobs'   => $query->paginate(10),
            'cutoff' => $cutoff,
        ];
    }

    protected function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}; ?>

<div>
    {{-- Page header --}}
    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h1 class="theme-text-strong text-2xl font-bold">Job Listings</h1>
            <p class="theme-text-muted mt-1 mb-1 text-sm">{{ $jobs->total() }} {{ Str::plural('opportunity', $jobs->total()) }} available</p>
        </div>

        @if(auth()->check() && auth()->user()->isCompany())
            <x-ui.button href="{{ route('jobs.create') }}" variant="primary">
                Post a Job
            </x-ui.button>
        @endif
    </div>

    {{-- Search + sort bar --}}
    <div class="flex gap-3 mb-6">
        <div class="relative flex-1" x-data="{}" x-on:click.outside="$wire.hideSuggestions()">
            <div
                class="theme-input-shell flex items-center gap-3 rounded-lg border px-4 py-2.5 transition-all duration-150"
                @class([
                    'rounded-b-none border-[color:var(--app-accent-soft-border)]' => $showSuggestions,
                ])
            >
                <svg class="theme-text-muted h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>

                <input
                    type="text"
                    wire:model.live.debounce.250ms="search"
                    placeholder="Search by title, skills, or requirements…"
                    class="theme-input flex-1 min-w-0 border-0 bg-transparent px-0 py-0 text-sm shadow-none outline-none"
                    autocomplete="off"
                    wire:focus="showSuggestionsIfEligible"
                    wire:keydown.arrow-down.prevent="moveSuggestionDown"
                    wire:keydown.arrow-up.prevent="moveSuggestionUp"
                    wire:keydown.enter.prevent="selectHighlightedSuggestion"
                    wire:keydown.escape="hideSuggestions"
                />

                @if($search)
                    <button wire:click="clearSearch" class="theme-text-muted shrink-0 rounded-full p-0.5 transition-colors hover:bg-[var(--app-panel-subtle-bg)] hover:text-[var(--app-text-strong)]" aria-label="Clear search">
                        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Autocomplete dropdown --}}
            @if($showSuggestions && $this->suggestions->isNotEmpty())
                <div class="theme-dropdown-panel absolute left-0 right-0 top-full z-50 overflow-hidden rounded-b-lg border border-t-0 shadow-lg" style="border-top-color: transparent;" wire:key="suggestions-dropdown">
                    @foreach($this->suggestions as $i => $suggestion)
                        <button
                            data-ac-item
                            wire:click="selectSuggestion('{{ addslashes($suggestion->title) }}')"
                            class="theme-table-divider flex w-full items-center gap-3 border-b px-4 py-2.5 text-left transition-colors focus:outline-none last:border-0"
                            @class([
                                'bg-[var(--app-panel-subtle-bg)]' => $highlightedSuggestion === $i,
                                'hover:bg-[var(--app-panel-subtle-bg)]' => $highlightedSuggestion !== $i,
                            ])
                        >
                            <div class="theme-icon-tile-accent flex h-8 w-8 shrink-0 items-center justify-center rounded font-bold uppercase" style="font-size:11px">
                                {{ Str::substr($suggestion->companyUser?->nickname ?? '?', 0, 2) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="theme-text-strong truncate text-sm font-medium">
                                    {!! preg_replace('/(' . preg_quote($search, '/') . ')/i', '<mark class="rounded-sm px-0.5 not-italic font-semibold" style="background: var(--app-accent-soft-bg); color: var(--app-accent-soft-fg);">$1</mark>', e($suggestion->title)) !!}
                                </div>
                                <div class="theme-text-muted mt-0.5 truncate text-xs">
                                    {{ $suggestion->companyUser?->nickname ?? 'Unknown company' }}
                                    @if($suggestion->salary)
                                        <span class="mx-1 opacity-50">&middot;</span>
                                        <span class="text-emerald-600 font-medium">{{ $suggestion->salary }}</span>
                                    @endif
                                </div>
                            </div>
                            <svg class="theme-text-muted h-[14px] w-[14px] shrink-0 opacity-60" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                            </svg>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <select wire:model.live="sort" class="theme-input shrink-0 rounded-lg border px-3 py-2.5 text-sm shadow-sm outline-none transition-all duration-150">
            <option value="latest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="salary_desc">Highest salary</option>
        </select>
    </div>

    {{-- Active search label --}}
    @if($search)
        <div class="flex items-center gap-2 mb-4">
            <span class="theme-text-muted text-sm">
                @if($jobs->total() === 0)
                    No results for
                @else
                    {{ $jobs->total() }} {{ Str::plural('result', $jobs->total()) }} for
                @endif
            </span>
            <span class="theme-pill inline-flex items-center gap-1.5 rounded-full border px-3 py-0.5 text-sm font-medium">
                "{{ $search }}"
                <button wire:click="clearSearch" class="transition-colors hover:opacity-80">
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
                    <div class="theme-panel group rounded-lg border px-5 py-4 shadow-sm transition-all duration-150 hover:border-[var(--app-accent-soft-border)] hover:shadow-md">
                        <div class="flex items-start gap-4">

                            {{-- Company logo placeholder --}}
                            <div class="theme-icon-tile flex h-12 w-12 shrink-0 items-center justify-center rounded-md font-bold uppercase transition-colors group-hover:bg-[var(--app-accent-soft-bg)] group-hover:text-[var(--app-accent-soft-fg)]" style="font-size:13px">
                                {{ Str::substr($job->companyUser?->nickname ?? '?', 0, 2) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="theme-text-strong text-base font-semibold leading-snug">
                                        <a href="{{ route('jobs.show', $job->idcode) }}" class="transition-colors hover:text-[var(--app-link-accent)]">
                                            {{ $job->title }}
                                        </a>
                                    </h2>
                                    @if($job->created_at->gt($cutoff))
                                        <span class="inline-flex shrink-0 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-xs font-semibold text-emerald-700">New</span>
                                    @endif
                                </div>

                                {{-- Company + salary --}}
                                <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                    <p class="theme-text-muted text-sm">{{ $job->companyUser?->nickname ?? 'Unknown company' }}</p>
                                    @if($job->salary)
                                        <span class="theme-text-muted opacity-50">&middot;</span>
                                        <span class="text-sm font-semibold text-emerald-700">{{ $job->salary }}</span>
                                    @endif
                                </div>

                                {{-- Requirement snippet --}}
                                <p class="theme-text-muted mt-2 text-sm leading-relaxed line-clamp-2">
                                    {{ $job->requirement }}
                                </p>

                                {{-- Footer meta --}}
                                <div class="mt-3 flex items-center justify-between gap-4">
                                    <span class="theme-text-muted flex items-center gap-1.5 text-xs">
                                        <svg style="width:13px;height:13px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        {{ $job->created_at->diffForHumans() }}
                                    </span>

                                    <a href="{{ route('jobs.show', $job->idcode) }}" class="theme-link shrink-0 text-xs font-medium underline-offset-2 transition-colors hover:underline">
                                        View details &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state
                        :title="$search ? 'No matching jobs found' : 'No job listings yet'"
                        :message="$search ? 'Try a different search term or browse all listings.' : 'Check back soon or, if you\'re hiring, post the first opportunity.'"
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
                                            <p class="theme-text-muted text-sm">Check back soon - new roles are posted regularly.</p>
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
            <div class="theme-panel rounded-lg border px-5 py-4 shadow-sm">
                <h3 class="theme-text-strong mb-3 text-sm font-semibold">Overview</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="theme-text-muted text-sm">Total listings</span>
                        <span class="theme-text-strong text-sm font-semibold">{{ $jobs->total() }}</span>
                    </div>
                    <div class="theme-table-divider border-t"></div>
                    <div class="flex items-center justify-between">
                        <span class="theme-text-muted text-sm">This page</span>
                        <span class="theme-text-strong text-sm font-semibold">
                            {{ $jobs->firstItem() ?? 0 }}–{{ $jobs->lastItem() ?? 0 }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Recently posted --}}
            @if($this->recentJobs->isNotEmpty())
                <div class="theme-panel rounded-lg border px-5 py-4 shadow-sm">
                    <h3 class="theme-text-strong mb-3 text-sm font-semibold">Recently posted</h3>
                    <div class="space-y-3">
                        @foreach($this->recentJobs as $recent)
                            <a href="{{ route('jobs.show', $recent->idcode) }}" class="flex items-start gap-2.5 group">
                                <div class="theme-icon-tile flex h-7 w-7 shrink-0 items-center justify-center rounded font-bold uppercase transition-colors group-hover:bg-[var(--app-accent-soft-bg)] group-hover:text-[var(--app-accent-soft-fg)]" style="font-size:10px">
                                    {{ Str::substr($recent->companyUser?->nickname ?? '?', 0, 2) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="theme-text-strong truncate text-xs font-medium transition-colors group-hover:text-[var(--app-link-accent)]">{{ $recent->title }}</p>
                                    <p class="theme-text-muted text-xs">{{ $recent->created_at->diffForHumans() }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- CTA for companies --}}
            @auth
                @if(auth()->user()->isCompany())
                    <div class="theme-panel-subtle rounded-lg border px-5 py-4">
                        <h3 class="theme-text-strong mb-1 text-sm font-semibold">Hiring?</h3>
                        <p class="theme-text-muted mb-3 text-xs leading-relaxed">Post a job and reach qualified candidates today.</p>
                        <x-ui.button href="{{ route('jobs.create') }}" variant="primary" size="sm">
                            Post a Job
                        </x-ui.button>
                    </div>
                @endif
            @else
                <div class="theme-panel rounded-lg border px-5 py-4 shadow-sm">
                    <h3 class="theme-text-strong mb-1 text-sm font-semibold">Get started</h3>
                    <p class="theme-text-muted mb-3 text-xs leading-relaxed">Sign up to apply for jobs or post opportunities.</p>
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
