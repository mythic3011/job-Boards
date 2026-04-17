<?php

use App\Models\JobPosting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DashboardService;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Admin - Jobs');

new class extends Component
{
    use WithPagination;

    private const PAGE_SIZE = 15;
    private const ALLOWED_SORTS = ['latest', 'oldest'];

    public string $search = '';
    public string $companyFilter = '';
    public string $sort = 'latest';
    public int $visibleCount = self::PAGE_SIZE;

    public function updatedSearch(): void
    {
        $this->resetInfinitePagination();
    }

    public function updatedCompanyFilter(): void
    {
        $this->resetInfinitePagination();
    }

    public function updatedSort(): void
    {
        if (! in_array($this->sort, self::ALLOWED_SORTS, true)) {
            $this->sort = 'latest';
        }

        $this->resetInfinitePagination();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetInfinitePagination();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->companyFilter = '';
        $this->sort = 'latest';
        $this->resetInfinitePagination();
    }

    public function deleteJob(string $jobId, DashboardService $dashboardService, AuditLogger $auditLogger): void
    {
        $this->authorize('admin.jobs.moderate');

        $job = JobPosting::findOrFail($jobId);

        $auditLogger->logBusinessEvent(
            eventType: 'admin.job.deleted',
            request: request(),
            targetType: 'job',
            targetIdcode: $job->idcode,
            meta: [
                'job_title' => $job->title,
                'company_user_id' => $job->company_user_id,
            ]
        );

        $job->delete();

        $dashboardService->clearCache();
        $this->dispatch('close-delete-modal');

        session()->flash('message', 'Job deleted successfully.');
    }

    public function with(): array
    {
        $query = JobPosting::with('companyUser');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'ilike', '%' . $this->search . '%')
                    ->orWhere('requirement', 'ilike', '%' . $this->search . '%');
            });
        }

        if ($this->companyFilter) {
            $query->where('company_user_id', $this->companyFilter);
        }

        match (in_array($this->sort, self::ALLOWED_SORTS, true) ? $this->sort : 'latest') {
            'oldest' => $query->oldest(),
            default => $query->latest(),
        };

        $companies = User::where('user_type', 'company')
            ->orderBy('nickname')
            ->get(['id', 'nickname']);

        return [
            'jobs' => $query->paginate($this->visibleCount),
            'companies' => $companies,
            'stats' => [
                'total_jobs' => JobPosting::count(),
                'company_accounts' => $companies->count(),
            ],
        ];
    }

    public function loadMore(): void
    {
        $this->visibleCount += self::PAGE_SIZE;
    }

    private function resetInfinitePagination(): void
    {
        $this->visibleCount = self::PAGE_SIZE;
        $this->resetPage();
    }
}; ?>

@php
    $activeFilterCount = collect([$search, $companyFilter, $sort !== 'latest' ? $sort : null])
        ->filter(fn ($value) => filled($value))
        ->count();
@endphp

<div x-data="{ showDeleteModal: false, pendingDeleteId: '' }" class="space-y-8">
    <div class="theme-hero-surface rounded-3xl border px-6 py-7 sm:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="theme-hero-eyebrow text-xs font-semibold uppercase tracking-[0.18em]">Admin Jobs</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">Job posting operations</h1>
                <p class="theme-text-muted mt-3 text-sm leading-6">
                    Review active listings, move between company context and applicant demand, and remove outdated postings without leaving the queue.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3 lg:min-w-[420px]">
                <div class="theme-hero-card rounded-2xl border px-4 py-4">
                    <p class="theme-text-muted text-xs uppercase tracking-[0.16em]">Total Jobs</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['total_jobs']) }}</p>
                    <p class="theme-text-muted mt-2 text-sm">Listings currently available across all companies.</p>
                </div>
                <div class="theme-hero-card rounded-2xl border px-4 py-4">
                    <p class="theme-text-muted text-xs uppercase tracking-[0.16em]">Company Accounts</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['company_accounts']) }}</p>
                    <p class="theme-text-muted mt-2 text-sm">Distinct employer workspaces feeding the jobs directory.</p>
                </div>
            </div>
        </div>
    </div>

    @if(session('message'))
        <x-ui.alert type="success" class="mb-6" dismissible>
            {{ session('message') }}
        </x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.8fr)_minmax(320px,1fr)]">
        <div class="space-y-6">
            <div class="theme-panel rounded-2xl border p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <x-ui.section-label class="mb-2">Listing Filters</x-ui.section-label>
                        <p class="theme-text-muted text-sm">Search job titles, scan requirements, and focus the queue by company.</p>
                    </div>
                    <div class="theme-pill inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold">
                        <span>{{ $jobs->total() }} {{ \Illuminate\Support\Str::plural('job', $jobs->total()) }}</span>
                        @if($activeFilterCount > 0)
                            <span class="theme-panel rounded-full border px-2 py-0.5 text-[11px] font-semibold">
                                {{ $activeFilterCount }} filter{{ $activeFilterCount > 1 ? 's' : '' }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <div class="relative min-w-64 flex-1">
                        <div class="theme-input-shell flex items-center gap-3 rounded-lg border px-4 py-2.5 transition-all duration-150">
                            <svg class="theme-text-muted h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Search by title or requirements"
                                class="theme-input flex-1 min-w-0 border-0 bg-transparent px-0 py-0 text-sm shadow-none outline-none"
                                autocomplete="off"
                            />
                            @if($search)
                                <button wire:click="clearSearch" class="theme-text-muted shrink-0 rounded-full p-0.5 transition-colors hover:bg-[var(--app-panel-subtle-bg)] hover:text-[var(--app-text-strong)] cursor-pointer" aria-label="Clear search">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <select wire:model.live="companyFilter" class="theme-input shrink-0 rounded-lg border px-3 py-2.5 text-sm shadow-sm outline-none transition-all duration-150 cursor-pointer">
                        <option value="">All companies</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->nickname }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="sort" class="theme-input shrink-0 rounded-lg border px-3 py-2.5 text-sm shadow-sm outline-none transition-all duration-150 cursor-pointer">
                        <option value="latest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                    </select>

                    @if($search || $companyFilter || $sort !== 'latest')
                        <button wire:click="clearFilters" class="theme-input shrink-0 rounded-lg border px-3 py-2.5 text-sm shadow-sm transition-colors hover:bg-[var(--app-panel-subtle-bg)] cursor-pointer">
                            Clear all
                        </button>
                    @endif
                </div>
            </div>

            <div class="theme-table-shell rounded-2xl border shadow-sm">
                <div class="theme-table-head flex items-center justify-between border-b px-6 py-4">
                    <h2 class="theme-text-muted text-sm font-semibold uppercase tracking-wider">Job Listings</h2>
                    <span class="theme-panel rounded-full border px-3 py-0.5 text-xs font-semibold">
                        {{ $jobs->count() }} on this page
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[760px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[34%]">
                            <col class="w-[20%]">
                            <col class="w-[18%]">
                            <col class="w-[14%]">
                            <col class="w-[14%]">
                        </colgroup>
                        <thead>
                            <tr class="theme-table-head theme-table-divider border-b">
                                <th class="theme-text-muted px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider">Listing</th>
                                <th class="theme-text-muted px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider">Company</th>
                                <th class="theme-text-muted px-6 py-3.5 text-center text-xs font-semibold uppercase tracking-wider">Applications</th>
                                <th class="theme-text-muted px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider">Created</th>
                                <th class="theme-text-muted px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="--tw-divide-opacity: 1; border-color: var(--app-panel-border);">
                            @forelse($jobs as $job)
                                @php $appCount = $job->applications()->count(); @endphp
                                <tr wire:key="admin-job-{{ $job->id }}" class="group transition-colors duration-150 hover:bg-[var(--app-panel-subtle-bg)]">
                                    <td class="px-6 py-4">
                                        <div class="theme-text-strong text-sm font-semibold transition-colors group-hover:text-[var(--app-link-accent)]">
                                            {{ $job->title }}
                                        </div>
                                        @if($job->salary)
                                            <div class="theme-text-muted mt-1 text-xs">{{ $job->salary }}</div>
                                        @else
                                            <div class="mt-1 text-xs italic text-gray-400">No salary stated</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="theme-panel-subtle theme-text-strong inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium">
                                            {{ $job->companyUser->nickname }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <a
                                            href="{{ route('admin.applications.index', ['companyFilter' => $job->company_user_id]) }}"
                                            class="theme-pill inline-flex min-w-[2.25rem] items-center justify-center rounded-full px-2.5 py-1 text-xs font-bold transition-colors cursor-pointer"
                                        >
                                            {{ $appCount }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="theme-text-strong text-sm font-medium">{{ $job->created_at->diffForHumans() }}</div>
                                        <div class="theme-text-muted mt-1 text-xs">{{ $job->created_at->format('M j, Y') }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col items-end gap-2">
                                            <a
                                                href="{{ route('jobs.show', $job->idcode) }}"
                                                class="theme-button theme-button-primary inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors duration-150 cursor-pointer"
                                            >
                                                Open
                                            </a>
                                            <a
                                                href="{{ route('admin.jobs.edit', $job->idcode) }}"
                                                class="theme-link inline-flex items-center gap-1.5 text-xs font-semibold transition-colors duration-150 cursor-pointer"
                                            >
                                                Edit
                                            </a>
                                            <button
                                                type="button"
                                                @click="pendingDeleteId = '{{ $job->id }}'; showDeleteModal = true"
                                                class="theme-alert-error inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors hover:brightness-95 cursor-pointer"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-20 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="theme-icon-tile rounded-full p-4">
                                                <svg class="theme-text-muted h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z" />
                                                </svg>
                                            </div>
                                            <p class="theme-text-strong text-sm font-semibold">No job postings found</p>
                                            @if($search && ($companyFilter || $sort !== 'latest'))
                                                <p class="theme-text-muted text-xs">Try adjusting your search or filters</p>
                                            @elseif($search)
                                                <p class="theme-text-muted text-xs">Try adjusting your search term</p>
                                            @elseif($companyFilter || $sort !== 'latest')
                                                <p class="theme-text-muted text-xs">Try adjusting your filters</p>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.infinite-scroll-pagination
                    :paginator="$jobs"
                    action="loadMore"
                    label="job"
                    key-name="admin-jobs"
                />
            </div>
        </div>

        <div class="space-y-6">
            <div>
                <div class="mb-4">
                    <x-ui.section-label class="mb-2">Queue posture</x-ui.section-label>
                    <p class="theme-text-muted text-sm">Quick context for moderation and cleanup before you open a listing.</p>
                </div>
                <x-ui.card class="space-y-4">
                    <div class="theme-panel-subtle rounded-2xl border px-4 py-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="theme-text-strong text-sm font-medium">Companies in view</p>
                            <p class="theme-text-strong text-2xl font-semibold">{{ number_format($stats['company_accounts']) }}</p>
                        </div>
                        <p class="theme-text-muted mt-2 text-sm">Employers currently contributing listings to this moderation queue.</p>
                    </div>

                    <div class="theme-panel-subtle rounded-2xl border px-4 py-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="theme-text-strong text-sm font-medium">Filters active</p>
                            <p class="theme-text-strong text-2xl font-semibold">{{ number_format($activeFilterCount) }}</p>
                        </div>
                        <p class="theme-text-muted mt-2 text-sm">Current scoping applied before acting on a listing or opening applications.</p>
                    </div>
                </x-ui.card>
            </div>

            <x-ui.card>
                <h2 class="theme-text-strong text-lg font-semibold">Operator Notes</h2>
                <div class="theme-text-muted mt-4 space-y-3 text-sm">
                    <p>Open a listing when you need full context, edit it when the post is still valid, and delete only when moderation policy calls for removal.</p>
                    <p>Application counts link straight into the filtered review queue so you can move from listing health to applicant posture without rebuilding filters.</p>
                </div>
            </x-ui.card>
        </div>
    </div>

    <div
        x-show="showDeleteModal"
        x-cloak
        style="display: none;"
        @close-delete-modal.window="showDeleteModal = false"
        x-on:keydown.escape.window="showDeleteModal = false"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            class="theme-modal-surface w-full max-w-lg rounded-xl"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.outside="showDeleteModal = false"
        >
            <div class="space-y-4 px-6 py-6">
                <div class="flex items-start gap-4">
                    <div class="theme-alert-error shrink-0 rounded-full border p-2">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="theme-text-strong text-lg font-medium">Delete job posting</h3>
                        <p class="theme-text-muted mt-2 text-sm">
                            Are you sure you want to remove this listing? This action cannot be undone.
                            <span class="theme-alert-error mt-2 inline-flex rounded-full border px-2 py-0.5 font-medium">Related applications are removed as part of this cleanup.</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="theme-table-head flex justify-end gap-x-4 border-t px-6 py-4 rounded-b-xl">
                <x-ui.button variant="outline" type="button" x-on:click="showDeleteModal = false">Cancel</x-ui.button>
                <x-ui.button variant="danger" type="button" @click="$wire.deleteJob(pendingDeleteId)" wire:loading.attr="disabled" wire:target="deleteJob">
                    <span wire:loading.remove wire:target="deleteJob">Delete Job</span>
                    <span wire:loading wire:target="deleteJob">Deleting...</span>
                </x-ui.button>
            </div>
        </div>
    </div>
</div>
