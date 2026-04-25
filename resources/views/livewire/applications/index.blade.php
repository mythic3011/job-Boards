<?php

use App\Models\Application;
use App\Services\ApplicationQueryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Applications');

new class extends Component
{
    use WithPagination;

    public ?string $jobIdcode = null;
    public string $search = '';
    public string $statusFilter = '';
    public string $sort = 'latest';

    public function mount(?string $jobIdcode = null): void
    {
        $this->jobIdcode = $jobIdcode ?? request()->query('jobIdcode');
    }

    private const ALLOWED_STATUSES = ['', 'pending', 'approved', 'rejected'];
    private const ALLOWED_SORTS    = ['latest', 'oldest'];

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedStatusFilter(): void
    {
        if (!in_array($this->statusFilter, self::ALLOWED_STATUSES, true)) {
            $this->statusFilter = '';
        }
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        if (!in_array($this->sort, self::ALLOWED_SORTS, true)) {
            $this->sort = 'latest';
        }
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function with(ApplicationQueryService $applicationQueryService): array
    {
        $user = Auth::user();
        $isCompany = $user->isCompany();
        $like = $this->likeOperator();

        // Build query directly for filtering/search/pagination
        if ($isCompany) {
            $query = Application::forCompanyJobs($user->id)
                ->with(['jobPosting.companyUser', 'applicantUser']);

            if ($this->jobIdcode) {
                $job = \App\Models\JobPosting::byIdcode($this->jobIdcode)
                    ->byCompany($user->id)
                    ->firstOrFail();
                $query->forJob($job->id);
            }
        } else {
            $query = Application::byApplicant($user->id)
                ->with(['jobPosting.companyUser']);
        }

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search, $isCompany, $like) {
                $q->whereHas('jobPosting', fn($j) => $j->where('title', $like, '%' . $search . '%'));
                if ($isCompany) {
                    $q->orWhereHas('applicantUser', fn($u) => $u->where('nickname', $like, '%' . $search . '%'));
                }
            });
        }

        if ($this->statusFilter && in_array($this->statusFilter, self::ALLOWED_STATUSES, true)) {
            $query->where('status', $this->statusFilter);
        }

        match (in_array($this->sort, self::ALLOWED_SORTS, true) ? $this->sort : 'latest') {
            'oldest' => $query->oldest(),
            default  => $query->latest(),
        };

        return [
            'applications' => $query->paginate(10),
            'isCompany'    => $isCompany,
        ];
    }

    protected function likeOperator(): string
    {
        return \DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}; ?>

<div>
    {{-- Page header --}}
    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h1 class="theme-text-strong text-2xl font-bold">
                @if($isCompany)
                    {{ $jobIdcode ? 'Applications for Job' : 'All Applications' }}
                @else
                    My Applications
                @endif
            </h1>
            <p class="theme-text-muted mt-1 text-sm">{{ $applications->total() }} {{ Str::plural('application', $applications->total()) }}</p>
        </div>
    </div>

    {{-- Search + filter bar --}}
    <div class="flex flex-wrap gap-3 mb-6">
        {{-- Search --}}
        <div class="relative flex-1 min-w-48">
            <div class="theme-input-shell flex items-center gap-3 rounded-lg border px-4 py-2.5 transition-all duration-150">
                <svg class="theme-text-muted h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    type="text"
                    wire:model.live.debounce.250ms="search"
                    placeholder="{{ $isCompany ? 'Search by job title or applicant…' : 'Search by job title…' }}"
                    class="theme-input flex-1 min-w-0 border-0 bg-transparent px-0 py-0 text-sm shadow-none outline-none"
                    autocomplete="off"
                />
                @if($search)
                    <button wire:click="clearSearch" class="theme-text-muted shrink-0 rounded-full p-0.5 transition-colors cursor-pointer hover:bg-[var(--app-panel-subtle-bg)] hover:text-[var(--app-text-strong)]" aria-label="Clear search">
                        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- Status filter --}}
        <select wire:model.live="statusFilter" class="theme-input shrink-0 rounded-lg border px-3 py-2.5 text-sm shadow-sm outline-none transition-all duration-150 cursor-pointer">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        </select>

        {{-- Sort --}}
        <select wire:model.live="sort" class="theme-input shrink-0 rounded-lg border px-3 py-2.5 text-sm shadow-sm outline-none transition-all duration-150 cursor-pointer">
            <option value="latest">Newest first</option>
            <option value="oldest">Oldest first</option>
        </select>
    </div>

    {{-- Active search label --}}
    @if($search)
        <div class="flex items-center gap-2 mb-4">
            <span class="theme-text-muted text-sm">Results for</span>
            <span class="theme-pill inline-flex items-center gap-1.5 rounded-full border px-3 py-0.5 text-sm font-medium">
                "{{ $search }}"
                <button wire:click="clearSearch" class="transition-colors cursor-pointer hover:opacity-80">
                    <svg style="width:12px;height:12px" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
        </div>
    @endif

    {{-- Application list --}}
    <div class="space-y-3">
        @forelse($applications as $application)
            @php
                $statusValue = $application->status->value;
                $statusLabel = match($statusValue) {
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    default    => 'Pending review',
                };
                $statusClasses = match($statusValue) {
                    'approved' => 'bg-green-50 text-green-700 border-green-200',
                    'rejected' => 'bg-red-50 text-red-700 border-red-200',
                    default    => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                };
                $dotClasses = match($statusValue) {
                    'approved' => 'bg-green-500',
                    'rejected' => 'bg-red-500',
                    default    => 'bg-yellow-500',
                };
            @endphp

            <div class="theme-panel group rounded-lg border px-5 py-4 shadow-sm transition-all duration-150 hover:border-[var(--app-accent-soft-border)] hover:shadow-md">
                <div class="flex items-start gap-4">

                    {{-- Avatar / company initials --}}
                    <div class="theme-icon-tile flex h-12 w-12 shrink-0 items-center justify-center rounded-md font-bold uppercase transition-colors group-hover:bg-[var(--app-accent-soft-bg)] group-hover:text-[var(--app-accent-soft-fg)]" style="font-size:13px">
                        @if($isCompany)
                            {{ Str::substr($application->applicantUser->nickname ?? '?', 0, 2) }}
                        @else
                            {{ Str::substr($application->jobPosting->companyUser?->nickname ?? '?', 0, 2) }}
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        {{-- Title row --}}
                        <div class="flex flex-wrap items-center gap-2">
                            @if($isCompany)
                                <h2 class="theme-text-strong text-base font-semibold">
                                    {{ $application->applicantUser->nickname }}
                                </h2>
                                <span class="theme-text-muted text-sm opacity-50">&middot;</span>
                                <a href="{{ route('jobs.show', $application->jobPosting->idcode) }}" class="theme-link text-sm transition-colors hover:underline underline-offset-2">
                                    {{ $application->jobPosting->title }}
                                </a>
                            @else
                                <h2 class="theme-text-strong text-base font-semibold leading-snug">
                                    <a href="{{ route('jobs.show', $application->jobPosting->idcode) }}" class="transition-colors hover:text-[var(--app-link-accent)]">
                                        {{ $application->jobPosting->title }}
                                    </a>
                                </h2>
                                <span class="theme-text-muted text-sm">{{ $application->jobPosting->companyUser?->nickname }}</span>
                            @endif

                        </div>

                        {{-- Cover message snippet --}}
                        @if($application->message)
                            <p class="theme-text-muted mt-1.5 text-sm leading-relaxed line-clamp-2">{{ $application->message }}</p>
                        @endif

                        {{-- Footer meta --}}
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="theme-text-muted flex flex-wrap items-center gap-3 text-xs">
                                {{-- Status badge --}}
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $statusClasses }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses }}"></span>
                                    {{ $statusLabel }}
                                </span>

                                {{-- CV --}}
                                @if($application->cv_original_name)
                                    <span class="flex items-center gap-1">
                                        <svg style="width:13px;height:13px" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        {{ $application->cv_original_name }}
                                    </span>
                                @endif

                                {{-- Time --}}
                                <span class="flex items-center gap-1">
                                    <svg style="width:13px;height:13px" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    {{ $application->created_at->diffForHumans() }}
                                </span>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-2">
                                <a href="{{ route('applications.show', ['idcode' => $application->idcode] + ($jobIdcode ? ['jobIdcode' => $jobIdcode] : [])) }}"
                                   class="theme-input inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-medium transition-all duration-150 cursor-pointer hover:border-[var(--app-accent-soft-border)] hover:bg-[var(--app-accent-soft-bg)] hover:text-[var(--app-accent-soft-fg)]">
                                    View details &rarr;
                                </a>
                                @if($application->cv_original_name)
                                    <a href="{{ route('applications.download-cv', $application->idcode) }}"
                                       class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition-all duration-150 hover:border-indigo-300 hover:bg-indigo-100 cursor-pointer">
                                        Download CV
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <x-ui.empty-state
                :title="$search || $statusFilter ? 'No matching applications' : ($isCompany ? 'No applications yet' : 'You have not applied to any jobs yet')"
                :message="$search || $statusFilter ? 'Try adjusting your search or filters.' : ($isCompany ? 'Applications will appear here once candidates apply.' : 'Browse open positions and submit your first application.')"
            >
                @if($search || $statusFilter)
                    <x-slot:action>
                        <x-ui.button wire:click="clearFilters" variant="outline">
                            Clear filters
                        </x-ui.button>
                    </x-slot:action>
                @elseif(!$isCompany)
                    <x-slot:action>
                        <x-ui.button href="{{ route('jobs.index') }}" variant="primary">
                            Browse jobs
                        </x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $applications->links() }}
    </div>
</div>
